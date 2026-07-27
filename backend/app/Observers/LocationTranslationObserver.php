<?php

namespace App\Observers;

use App\Application\Catalog\CatalogCacheService;
use App\Models\CityTranslation;
use App\Models\CountryTranslation;
use App\Models\DistrictTranslation;

class LocationTranslationObserver
{
    public function __construct(
        private readonly CatalogCacheService $cache,
    ) {}

    public function saved(CountryTranslation|CityTranslation|DistrictTranslation $translation): void
    {
        $this->cache->flushLocations();
    }

    public function deleted(CountryTranslation|CityTranslation|DistrictTranslation $translation): void
    {
        $this->cache->flushLocations();
    }
}
