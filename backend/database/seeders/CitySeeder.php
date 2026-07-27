<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Database\Seeders\Concerns\SeedsTranslations;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    use SeedsTranslations;

    public function run(): void
    {
        $country = Country::query()->where('code', 'QA')->firstOrFail();

        $cities = [
            ['slug' => 'doha', 'sort_order' => 1, 'ar' => 'الدوحة', 'en' => 'Doha'],
            ['slug' => 'al-wakrah', 'sort_order' => 2, 'ar' => 'الوكرة', 'en' => 'Al Wakrah'],
            ['slug' => 'al-khor', 'sort_order' => 3, 'ar' => 'الخور', 'en' => 'Al Khor'],
            ['slug' => 'lusail', 'sort_order' => 4, 'ar' => 'لوسيل', 'en' => 'Lusail'],
        ];

        foreach ($cities as $definition) {
            $city = City::query()->create([
                'country_id' => $country->id,
                'slug' => $definition['slug'],
                'sort_order' => $definition['sort_order'],
                'is_active' => true,
            ]);

            $this->seedTranslations($city, [
                'ar' => $definition['ar'],
                'en' => $definition['en'],
            ]);
        }
    }
}
