<?php

namespace Tests\Feature\Category;

use App\Application\Catalog\CatalogCacheService;
use App\Domain\Category\Enums\CategoryStatus;
use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            PlatformSettingsSeeder::class,
            CategorySeeder::class,
        ]);
    }

    public function test_categories_index_returns_active_flat_list_with_envelope(): void
    {
        Category::query()->create([
            'slug' => 'hidden-category',
            'sort_order' => 99,
            'status' => CategoryStatus::Hidden,
        ]);

        $response = $this->getJson('/api/v1/categories?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'categories' => [
                        '*' => ['id', 'slug', 'name', 'icon', 'sort_order', 'listing_count'],
                    ],
                ],
            ]);

        $slugs = collect($response->json('data.categories'))->pluck('slug');

        $this->assertTrue($slugs->contains('vehicles'));
        $this->assertFalse($slugs->contains('hidden-category'));
    }

    public function test_categories_tree_returns_nested_active_categories(): void
    {
        $response = $this->getJson('/api/v1/categories/tree?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.categories.0.slug', 'vehicles')
            ->assertJsonPath('data.categories.0.children.0.slug', 'cars')
            ->assertJsonPath('data.categories.0.children.0.children.0.slug', 'sedans');
    }

    public function test_category_show_resolves_by_slug_and_locale(): void
    {
        $this->getJson('/api/v1/categories/cars?locale=en')
            ->assertOk()
            ->assertJsonPath('data.category.slug', 'cars')
            ->assertJsonPath('data.category.name', 'Cars');

        $this->getJson('/api/v1/categories/cars', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'سيارات');
    }

    public function test_category_show_returns_not_found_for_missing_slug(): void
    {
        $this->getJson('/api/v1/categories/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_category_show_hides_inactive_categories(): void
    {
        Category::query()->create([
            'slug' => 'inactive-leaf',
            'sort_order' => 1,
            'status' => CategoryStatus::Hidden,
        ]);

        $this->getJson('/api/v1/categories/inactive-leaf')
            ->assertNotFound();
    }

    public function test_category_resource_excludes_internal_status_field(): void
    {
        $response = $this->getJson('/api/v1/categories/vehicles?locale=en');

        $response->assertOk()
            ->assertJsonMissingPath('data.category.status')
            ->assertJsonMissingPath('data.category.parent_id')
            ->assertJsonMissingPath('data.category.deleted_at');
    }

    public function test_category_tree_is_cached_and_invalidated(): void
    {
        Cache::flush();

        $this->getJson('/api/v1/categories/tree?locale=en')->assertOk();
        $cacheKey = config('catalog.cache_keys.categories_tree').':en';
        $this->assertTrue(Cache::has($cacheKey));

        Category::query()->create([
            'slug' => 'cache-bust',
            'sort_order' => 50,
            'status' => CategoryStatus::Active,
        ]);

        $this->assertFalse(Cache::has($cacheKey));

        app(CatalogCacheService::class)->flushCategories();
    }
}
