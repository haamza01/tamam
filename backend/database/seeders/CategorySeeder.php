<?php

namespace Database\Seeders;

use App\Application\Catalog\CatalogCacheService;
use App\Domain\Category\Enums\CategoryStatus;
use App\Models\Category;
use Database\Seeders\Concerns\SeedsTranslations;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use SeedsTranslations;

    public function run(): void
    {
        $tree = [
            [
                'slug' => 'vehicles',
                'icon' => 'car',
                'sort_order' => 1,
                'translations' => ['ar' => 'مركبات', 'en' => 'Vehicles'],
                'children' => [
                    [
                        'slug' => 'cars',
                        'sort_order' => 1,
                        'translations' => ['ar' => 'سيارات', 'en' => 'Cars'],
                        'children' => [
                            ['slug' => 'sedans', 'sort_order' => 1, 'translations' => ['ar' => 'سيدان', 'en' => 'Sedans']],
                            ['slug' => 'suvs', 'sort_order' => 2, 'translations' => ['ar' => 'دفع رباعي', 'en' => 'SUVs']],
                        ],
                    ],
                    ['slug' => 'motorcycles', 'sort_order' => 2, 'translations' => ['ar' => 'دراجات نارية', 'en' => 'Motorcycles']],
                    ['slug' => 'trucks', 'sort_order' => 3, 'translations' => ['ar' => 'شاحنات', 'en' => 'Trucks']],
                ],
            ],
            [
                'slug' => 'real-estate',
                'icon' => 'home',
                'sort_order' => 2,
                'translations' => ['ar' => 'عقارات', 'en' => 'Real Estate'],
                'children' => [
                    ['slug' => 'apartments', 'sort_order' => 1, 'translations' => ['ar' => 'شقق', 'en' => 'Apartments']],
                    ['slug' => 'villas', 'sort_order' => 2, 'translations' => ['ar' => 'فلل', 'en' => 'Villas']],
                    ['slug' => 'offices', 'sort_order' => 3, 'translations' => ['ar' => 'مكاتب', 'en' => 'Offices']],
                    ['slug' => 'land', 'sort_order' => 4, 'translations' => ['ar' => 'أراضي', 'en' => 'Land']],
                ],
            ],
            [
                'slug' => 'jobs',
                'icon' => 'briefcase',
                'sort_order' => 3,
                'translations' => ['ar' => 'وظائف', 'en' => 'Jobs'],
                'children' => [
                    ['slug' => 'full-time', 'sort_order' => 1, 'translations' => ['ar' => 'دوام كامل', 'en' => 'Full-time']],
                    ['slug' => 'part-time', 'sort_order' => 2, 'translations' => ['ar' => 'دوام جزئي', 'en' => 'Part-time']],
                    ['slug' => 'contract', 'sort_order' => 3, 'translations' => ['ar' => 'عقود', 'en' => 'Contract']],
                ],
            ],
            [
                'slug' => 'services',
                'icon' => 'tool',
                'sort_order' => 4,
                'translations' => ['ar' => 'خدمات', 'en' => 'Services'],
                'children' => [
                    ['slug' => 'home-services', 'sort_order' => 1, 'translations' => ['ar' => 'خدمات منزلية', 'en' => 'Home']],
                    ['slug' => 'professional-services', 'sort_order' => 2, 'translations' => ['ar' => 'خدمات مهنية', 'en' => 'Professional']],
                    ['slug' => 'education-services', 'sort_order' => 3, 'translations' => ['ar' => 'تعليم', 'en' => 'Education']],
                ],
            ],
            [
                'slug' => 'electronics',
                'icon' => 'device',
                'sort_order' => 5,
                'translations' => ['ar' => 'إلكترونيات', 'en' => 'Electronics'],
                'children' => [
                    ['slug' => 'phones', 'sort_order' => 1, 'translations' => ['ar' => 'هواتف', 'en' => 'Phones']],
                    ['slug' => 'computers', 'sort_order' => 2, 'translations' => ['ar' => 'حواسيب', 'en' => 'Computers']],
                    ['slug' => 'tvs', 'sort_order' => 3, 'translations' => ['ar' => 'تلفزيونات', 'en' => 'TVs']],
                ],
            ],
            ['slug' => 'furniture', 'icon' => 'sofa', 'sort_order' => 6, 'translations' => ['ar' => 'أثاث', 'en' => 'Furniture']],
            ['slug' => 'fashion', 'icon' => 'shirt', 'sort_order' => 7, 'translations' => ['ar' => 'أزياء', 'en' => 'Fashion']],
            ['slug' => 'sports', 'icon' => 'ball', 'sort_order' => 8, 'translations' => ['ar' => 'رياضة', 'en' => 'Sports']],
            ['slug' => 'pets', 'icon' => 'paw', 'sort_order' => 9, 'translations' => ['ar' => 'حيوانات أليفة', 'en' => 'Pets']],
            ['slug' => 'general-items', 'icon' => 'box', 'sort_order' => 10, 'translations' => ['ar' => 'متنوعات', 'en' => 'General Items']],
        ];

        foreach ($tree as $node) {
            $this->createNode($node);
        }

        app(CatalogCacheService::class)->flushCategories();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function createNode(array $node, ?string $parentId = null): void
    {
        $category = Category::query()->create([
            'parent_id' => $parentId,
            'slug' => $node['slug'],
            'icon' => $node['icon'] ?? null,
            'sort_order' => $node['sort_order'] ?? 0,
            'status' => CategoryStatus::Active,
        ]);

        $this->seedTranslations($category, $node['translations']);

        foreach ($node['children'] ?? [] as $child) {
            $this->createNode($child, $category->id);
        }
    }
}
