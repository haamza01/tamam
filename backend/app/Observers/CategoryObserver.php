<?php

namespace App\Observers;

use App\Application\Catalog\CatalogCacheService;
use App\Models\Category;

class CategoryObserver
{
    public function __construct(
        private readonly CatalogCacheService $cache,
    ) {}

    public function saved(Category $category): void
    {
        $this->cache->flushCategories();
    }

    public function deleted(Category $category): void
    {
        $this->cache->flushCategories();
    }
}
