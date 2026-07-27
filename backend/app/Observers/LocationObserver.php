<?php

namespace App\Observers;

use App\Application\Catalog\CatalogCacheService;
use App\Models\City;
use App\Models\Country;
use App\Models\District;

class LocationObserver
{
    public function __construct(
        private readonly CatalogCacheService $cache,
    ) {}

    public function saved(Country|City|District $model): void
    {
        $this->cache->flushLocations();
    }

    public function deleted(Country|City|District $model): void
    {
        $this->cache->flushLocations();
    }
}
