<?php

namespace App\Application\Catalog;

use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    /** @var list<string> */
    private const LOCALES = ['ar', 'en'];

    public function remember(string $key, callable $callback): mixed
    {
        $ttl = (int) config('catalog.cache_ttl', 3600);

        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }

    public function flushCategories(): void
    {
        $this->forgetLocaleKeys([
            (string) config('catalog.cache_keys.categories_flat'),
            (string) config('catalog.cache_keys.categories_tree'),
        ]);
    }

    public function flushLocations(): void
    {
        $this->forgetLocaleKeys([
            (string) config('catalog.cache_keys.locations_flat'),
            (string) config('catalog.cache_keys.locations_tree'),
        ]);
    }

    public function flushAll(): void
    {
        $this->flushCategories();
        $this->flushLocations();
    }

    /**
     * @param  list<string>  $baseKeys
     */
    private function forgetLocaleKeys(array $baseKeys): void
    {
        try {
            foreach ($baseKeys as $baseKey) {
                foreach (self::LOCALES as $locale) {
                    Cache::forget("{$baseKey}:{$locale}");
                }
            }
        } catch (\Throwable) {
            // Cache backend may be unavailable in some local environments.
        }
    }
}
