<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use Database\Seeders\Concerns\SeedsTranslations;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    use SeedsTranslations;

    public function run(): void
    {
        $doha = City::query()->where('slug', 'doha')->firstOrFail();

        $districts = [
            ['slug' => 'west-bay', 'sort_order' => 1, 'ar' => 'الخليج الغربي', 'en' => 'West Bay'],
            ['slug' => 'the-pearl', 'sort_order' => 2, 'ar' => 'اللؤلؤة', 'en' => 'The Pearl'],
            ['slug' => 'al-sadd', 'sort_order' => 3, 'ar' => 'السد', 'en' => 'Al Sadd'],
            ['slug' => 'al-rayyan', 'sort_order' => 4, 'ar' => 'الريان', 'en' => 'Al Rayyan'],
            ['slug' => 'lusail-marina', 'sort_order' => 5, 'ar' => 'مارينا لوسيل', 'en' => 'Lusail Marina'],
        ];

        foreach ($districts as $definition) {
            $district = District::query()->create([
                'city_id' => $doha->id,
                'slug' => $definition['slug'],
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
            ]);

            $this->seedTranslations($district, [
                'ar' => $definition['ar'],
                'en' => $definition['en'],
            ]);
        }
    }
}
