<?php

namespace App\Application\Search;

use App\Domain\Category\Enums\CategoryStatus;
use App\Domain\Search\Exceptions\SearchException;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PublicListingQueryBuilder
{
    public function __construct(
        private readonly CategoryDescendantResolver $categoryDescendants,
        private readonly SearchAttributeFilterApplier $attributeFilters,
    ) {}

    /**
     * @return Builder<Listing>
     */
    public function base(): Builder
    {
        return Listing::query()
            ->publiclyVisible()
            ->whereHas('category', function (Builder $query): void {
                $query->where('status', CategoryStatus::Active)
                    ->whereNull('deleted_at');
            })
            ->whereHas('city', function (Builder $query): void {
                $query->where('is_active', true);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('district_id')
                    ->orWhereHas('district', function (Builder $districtQuery): void {
                        $districtQuery->where('is_active', true);
                    });
            });
    }

    /**
     * @param  Builder<Listing>  $query
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $query, array $filters): void
    {
        $categoryScopeIds = null;

        if (! empty($filters['category_id'])) {
            $categoryScopeIds = $this->categoryDescendants->idsIncludingSelf((string) $filters['category_id']);
            $query->whereIn('category_id', $categoryScopeIds);
        }

        if (! empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', $filters['district_id']);
        }

        if (! empty($filters['price_min'])) {
            $query->whereNotNull('price')
                ->where('price', '>=', $filters['price_min']);
        }

        if (! empty($filters['price_max'])) {
            $query->whereNotNull('price')
                ->where('price', '<=', $filters['price_max']);
        }

        if (! empty($filters['price_type'])) {
            $query->where('price_type', $filters['price_type']);
        }

        if (! empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        if (! empty($filters['attributes']) && is_array($filters['attributes'])) {
            $this->attributeFilters->apply($query, $filters, $categoryScopeIds);
        }
    }

    /**
     * @param  Builder<Listing>  $query
     */
    public function applySorting(Builder $query, string $sort, ?string $keyword = null): void
    {
        match ($sort) {
            'relevance' => $this->applyRelevanceSort($query, $keyword),
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            'price_asc' => $query->orderByRaw('price IS NULL')->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByRaw('price IS NULL')->orderByDesc('price')->orderByDesc('id'),
            'most_viewed' => $this->applyMostViewedSort($query),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function validateLocationFilters(array $filters): void
    {
        if (! empty($filters['city_id'])) {
            $city = City::query()->whereKey($filters['city_id'])->where('is_active', true)->first();

            if ($city === null) {
                throw new SearchException(
                    errorCode: 'search.invalid_location',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ['city' => ['search.invalid_location']],
                );
            }
        }

        if (! empty($filters['district_id'])) {
            if (empty($filters['city_id'])) {
                throw new SearchException(
                    errorCode: 'search.invalid_location',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ['district' => ['search.district_requires_city']],
                );
            }

            $district = District::query()
                ->whereKey($filters['district_id'])
                ->where('city_id', $filters['city_id'])
                ->where('is_active', true)
                ->first();

            if ($district === null) {
                throw new SearchException(
                    errorCode: 'search.invalid_location',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ['district' => ['search.invalid_location']],
                );
            }
        }
    }

    public function validateCategoryFilter(?string $categoryId): void
    {
        if ($categoryId === null || $categoryId === '') {
            return;
        }

        $category = Category::query()
            ->whereKey($categoryId)
            ->where('status', CategoryStatus::Active)
            ->whereNull('deleted_at')
            ->first();

        if ($category === null) {
            throw new SearchException(
                errorCode: 'search.invalid_category',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['category' => ['search.invalid_category']],
            );
        }
    }

    /**
     * @param  Builder<Listing>  $query
     */
    private function applyRelevanceSort(Builder $query, ?string $keyword): void
    {
        if ($keyword !== null && DB::connection()->getDriverName() === 'pgsql') {
            $config = (string) config('search.fts_config', 'simple');
            $query->orderByRaw(SearchSql::FTS_RANK, [$config, $keyword]);
        }

        $query->orderByDesc('published_at')->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @param  Builder<Listing>  $query
     */
    private function applyMostViewedSort(Builder $query): void
    {
        $query->leftJoin('listing_statistics as ls', 'ls.listing_id', '=', 'listings.id')
            ->select('listings.*')
            ->orderByDesc('ls.views_count')
            ->orderByDesc('listings.published_at')
            ->orderByDesc('listings.id');
    }
}
