<?php

namespace App\Application\Location;

use App\Application\Catalog\CatalogCacheService;
use App\Models\City;
use App\Models\Country;
use App\Models\District;
use Illuminate\Support\Collection;

class LocationService
{
    public function __construct(
        private readonly CatalogCacheService $cache,
    ) {}

    /**
     * @return array{
     *     countries: Collection<int, Country>,
     *     cities: Collection<int, City>,
     *     districts: Collection<int, District>
     * }
     */
    public function flatActive(string $locale): array
    {
        $cacheKey = (string) config('catalog.cache_keys.locations_flat').':'.$locale;

        /** @var array{countries: Collection<int, Country>, cities: Collection<int, City>, districts: Collection<int, District>} $data */
        $data = $this->cache->remember($cacheKey, fn (): array => $this->loadFlatActive($locale));

        return $data;
    }

    /**
     * @return Collection<int, Country>
     */
    public function treeActive(string $locale): Collection
    {
        $cacheKey = (string) config('catalog.cache_keys.locations_tree').':'.$locale;

        /** @var Collection<int, Country> $tree */
        $tree = $this->cache->remember($cacheKey, fn (): Collection => $this->buildTree($locale));

        return $tree;
    }

    /**
     * @return array{
     *     countries: Collection<int, Country>,
     *     cities: Collection<int, City>,
     *     districts: Collection<int, District>
     * }
     */
    private function loadFlatActive(string $locale): array
    {
        $translationScope = fn ($query) => $query->whereIn('locale', [$locale, 'ar', 'en']);

        $countries = Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['translations' => $translationScope])
            ->get();

        $cities = City::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['translations' => $translationScope])
            ->get();

        $districts = District::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['translations' => $translationScope])
            ->get();

        return compact('countries', 'cities', 'districts');
    }

    /**
     * @return Collection<int, Country>
     */
    private function buildTree(string $locale): Collection
    {
        $translationScope = fn ($query) => $query->whereIn('locale', [$locale, 'ar', 'en']);

        return Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'translations' => $translationScope,
                'cities' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with([
                        'translations' => $translationScope,
                        'districts' => fn ($districtQuery) => $districtQuery
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->with(['translations' => $translationScope]),
                    ]),
            ])
            ->get();
    }
}
