<?php

namespace App\Observers;

use App\Application\Catalog\CatalogCacheService;
use App\Models\CategoryTranslation;

class CategoryTranslationObserver
{
    public function __construct(
        private readonly CatalogCacheService $cache,
    ) {}

    public function saved(CategoryTranslation $translation): void
    {
        $this->cache->flushCategories();
    }

    public function deleted(CategoryTranslation $translation): void
    {
        $this->cache->flushCategories();
    }
}
