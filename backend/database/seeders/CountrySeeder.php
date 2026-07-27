<?php

namespace Database\Seeders;

use App\Models\Country;
use Database\Seeders\Concerns\SeedsTranslations;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    use SeedsTranslations;

    public function run(): void
    {
        $country = Country::query()->create([
            'code' => 'QA',
            'slug' => 'qatar',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->seedTranslations($country, [
            'ar' => 'قطر',
            'en' => 'Qatar',
        ]);
    }
}
