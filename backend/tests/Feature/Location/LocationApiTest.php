<?php

namespace Tests\Feature\Location;

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use Database\Seeders\CitySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class,
            PlatformSettingsSeeder::class,
            CountrySeeder::class,
            CitySeeder::class,
            DistrictSeeder::class,
        ]);
    }

    public function test_locations_index_returns_flat_active_locations(): void
    {
        Country::query()->create([
            'code' => 'XX',
            'slug' => 'inactive-country',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/locations?locale=en');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'locations' => [
                        'countries' => [['id', 'code', 'slug', 'name', 'sort_order']],
                        'cities' => [['id', 'country_id', 'slug', 'name', 'sort_order']],
                        'districts' => [['id', 'city_id', 'slug', 'name', 'sort_order']],
                    ],
                ],
            ]);

        $countrySlugs = collect($response->json('data.locations.countries'))->pluck('slug');
        $this->assertTrue($countrySlugs->contains('qatar'));
        $this->assertFalse($countrySlugs->contains('inactive-country'));
    }

    public function test_locations_tree_returns_country_city_district_hierarchy(): void
    {
        $response = $this->getJson('/api/v1/locations/tree?locale=en');

        $response->assertOk()
            ->assertJsonPath('data.locations.0.slug', 'qatar')
            ->assertJsonPath('data.locations.0.cities.0.slug', 'doha')
            ->assertJsonPath('data.locations.0.cities.0.districts.0.slug', 'west-bay');
    }

    public function test_locations_respect_accept_language_header(): void
    {
        $this->getJson('/api/v1/locations/tree', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.locations.0.name', 'قطر')
            ->assertJsonPath('data.locations.0.cities.0.name', 'الدوحة');
    }

    public function test_inactive_city_is_excluded_from_public_endpoints(): void
    {
        $country = Country::query()->where('slug', 'qatar')->firstOrFail();

        City::query()->create([
            'country_id' => $country->id,
            'slug' => 'inactive-city',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/locations?locale=en');
        $citySlugs = collect($response->json('data.locations.cities'))->pluck('slug');

        $this->assertFalse($citySlugs->contains('inactive-city'));
    }

    public function test_inactive_district_is_excluded_from_tree(): void
    {
        $doha = City::query()->where('slug', 'doha')->firstOrFail();

        District::query()->create([
            'city_id' => $doha->id,
            'slug' => 'inactive-district',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        $districtSlugs = collect(
            $this->getJson('/api/v1/locations/tree?locale=en')->json('data.locations.0.cities.0.districts')
        )->pluck('slug');

        $this->assertFalse($districtSlugs->contains('inactive-district'));
    }

    public function test_location_tree_is_cached_and_invalidated(): void
    {
        Cache::flush();

        $this->getJson('/api/v1/locations/tree?locale=en')->assertOk();
        $cacheKey = config('catalog.cache_keys.locations_tree').':en';
        $this->assertTrue(Cache::has($cacheKey));

        $country = Country::query()->where('slug', 'qatar')->firstOrFail();
        City::query()->create([
            'country_id' => $country->id,
            'slug' => 'cache-bust-city',
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }
}
