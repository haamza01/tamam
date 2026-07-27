<?php

namespace Database\Seeders;

use App\Domain\Category\Enums\AttributeType;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\CategoryAttributeOption;
use Illuminate\Database\Seeder;

class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAttributes('sedans', [
            [
                'slug' => 'brand',
                'type' => AttributeType::Dropdown,
                'required' => true,
                'filterable' => true,
                'options' => ['Toyota', 'Nissan', 'Hyundai'],
                'translations' => ['ar' => 'الماركة', 'en' => 'Brand'],
            ],
            [
                'slug' => 'year',
                'type' => AttributeType::Number,
                'required' => true,
                'filterable' => true,
                'min_value' => 1980,
                'max_value' => 2026,
                'translations' => ['ar' => 'السنة', 'en' => 'Year'],
            ],
            [
                'slug' => 'mileage',
                'type' => AttributeType::Number,
                'required' => true,
                'unit' => 'km',
                'translations' => ['ar' => 'المسافة', 'en' => 'Mileage'],
            ],
        ]);

        $this->seedAttributes('apartments', [
            [
                'slug' => 'bedrooms',
                'type' => AttributeType::Number,
                'required' => true,
                'translations' => ['ar' => 'غرف النوم', 'en' => 'Bedrooms'],
            ],
            [
                'slug' => 'area',
                'type' => AttributeType::Number,
                'required' => true,
                'unit' => 'sqm',
                'translations' => ['ar' => 'المساحة', 'en' => 'Area'],
            ],
        ]);

        $this->seedAttributes('phones', [
            [
                'slug' => 'brand',
                'type' => AttributeType::Dropdown,
                'required' => true,
                'filterable' => true,
                'options' => ['Apple', 'Samsung', 'Huawei'],
                'translations' => ['ar' => 'الماركة', 'en' => 'Brand'],
            ],
            [
                'slug' => 'storage',
                'type' => AttributeType::Dropdown,
                'required' => false,
                'options' => ['64GB', '128GB', '256GB'],
                'translations' => ['ar' => 'السعة', 'en' => 'Storage'],
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function seedAttributes(string $categorySlug, array $definitions): void
    {
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();

        foreach ($definitions as $index => $definition) {
            $attribute = CategoryAttribute::query()->create([
                'category_id' => $category->id,
                'slug' => $definition['slug'],
                'type' => $definition['type'],
                'required' => $definition['required'] ?? false,
                'searchable' => $definition['searchable'] ?? false,
                'filterable' => $definition['filterable'] ?? false,
                'sort_order' => $index + 1,
                'unit' => $definition['unit'] ?? null,
                'min_value' => $definition['min_value'] ?? null,
                'max_value' => $definition['max_value'] ?? null,
            ]);

            foreach (['ar', 'en'] as $locale) {
                $attribute->translations()->create([
                    'locale' => $locale,
                    'name' => $definition['translations'][$locale],
                ]);
            }

            foreach ($definition['options'] ?? [] as $optionIndex => $optionValue) {
                $option = CategoryAttributeOption::query()->create([
                    'category_attribute_id' => $attribute->id,
                    'value' => $optionValue,
                    'sort_order' => $optionIndex + 1,
                ]);

                foreach (['ar', 'en'] as $locale) {
                    $option->translations()->create([
                        'locale' => $locale,
                        'label' => $optionValue,
                    ]);
                }
            }
        }
    }
}
