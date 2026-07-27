<?php

namespace App\Application\Search;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PopularSearchService
{
    /**
     * @return Collection<int, array{term: string, rank: int}>
     */
    public function popular(): Collection
    {
        $cacheKey = (string) config('search.cache_keys.popular');
        $maxResults = (int) config('search.popular.max_results');

        /** @var Collection<int, array{term: string, rank: int}> $terms */
        $terms = Cache::remember(
            $cacheKey,
            (int) config('search.popular.cache_ttl'),
            fn (): Collection => $this->loadTerms($maxResults),
        );

        return $terms;
    }

    /**
     * @return Collection<int, array{term: string, rank: int}>
     */
    private function loadTerms(int $maxResults): Collection
    {
        $rawTerms = config('popular_searches.terms', []);

        return collect($rawTerms)
            ->map(fn (mixed $term): ?string => is_string($term) ? trim($term) : null)
            ->filter(fn (?string $term): bool => $term !== null && $term !== '' && mb_strlen($term) >= (int) config('search.keyword.min_length'))
            ->unique(fn (string $term): string => mb_strtolower($term))
            ->take($maxResults)
            ->values()
            ->map(fn (string $term, int $index): array => [
                'term' => $term,
                'rank' => $index + 1,
            ]);
    }
}
