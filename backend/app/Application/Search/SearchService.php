<?php

namespace App\Application\Search;

use App\Application\Listing\ListingExpiryService;
use App\Domain\Search\Exceptions\SearchException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SearchService
{
    public function __construct(
        private readonly ListingExpiryService $listingExpiry,
        private readonly SearchQueryParser $queryParser,
        private readonly PublicListingQueryBuilder $queryBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $this->listingExpiry->expireDue();

        if (
            isset($filters['price_min'], $filters['price_max'])
            && (float) $filters['price_min'] > (float) $filters['price_max']
        ) {
            throw new SearchException(
                errorCode: 'search.invalid_price_range',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['price_max' => ['search.invalid_price_range']],
            );
        }

        $this->queryBuilder->validateCategoryFilter($filters['category_id'] ?? null);
        $this->queryBuilder->validateLocationFilters($filters);

        $parsed = $this->queryParser->parse($filters['keyword'] ?? null);
        $sort = $this->resolveSort((string) ($filters['sort'] ?? ''), $parsed['tsquery'] !== null);
        $perPage = min(
            max(1, (int) ($filters['per_page'] ?? config('search.pagination.default'))),
            (int) config('search.pagination.max'),
        );

        $query = $this->queryBuilder->base()
            ->with(['category.translations', 'city.translations', 'user', 'images']);

        if ($parsed['tsquery'] !== null && DB::connection()->getDriverName() === 'pgsql') {
            $config = (string) config('search.fts_config', 'simple');
            $query->whereRaw('search_vector @@ to_tsquery(?, ?)', [$config, $parsed['tsquery']]);
        } elseif ($parsed['keyword'] !== null && DB::connection()->getDriverName() !== 'pgsql') {
            $query->where(function (Builder $builder) use ($parsed): void {
                $like = '%'.$parsed['keyword'].'%';
                $builder->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        $this->queryBuilder->applyFilters($query, $filters);
        $this->queryBuilder->applySorting($query, $sort, $parsed['tsquery']);

        return $query->paginate($perPage, ['listings.*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }

    private function resolveSort(string $sort, bool $hasKeyword): string
    {
        $allowed = ['relevance', 'newest', 'oldest', 'price_asc', 'price_desc', 'most_viewed', 'latest'];

        if ($sort === '' || ! in_array($sort, $allowed, true)) {
            return $hasKeyword ? 'relevance' : 'newest';
        }

        if ($sort === 'latest') {
            return 'newest';
        }

        if ($sort === 'relevance' && ! $hasKeyword) {
            return 'newest';
        }

        return $sort;
    }
}
