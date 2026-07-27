<?php

namespace App\Application\Search;

use App\Application\Listing\ListingExpiryService;
use App\Domain\Category\Enums\CategoryStatus;
use App\Domain\Search\Exceptions\SearchException;
use App\Models\CategoryTranslation;
use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SearchSuggestionService
{
    public function __construct(
        private readonly ListingExpiryService $listingExpiry,
        private readonly SearchQueryParser $queryParser,
    ) {}

    /**
     * @return Collection<int, array{type: string, value: string, label: string|null}>
     */
    public function suggest(string $rawPrefix, string $locale): Collection
    {
        $this->listingExpiry->expireDue();

        $prefix = $this->queryParser->normalize($rawPrefix);
        $minLength = (int) config('search.suggestions.min_prefix_length');
        $maxResults = (int) config('search.suggestions.max_results');

        if (mb_strlen($prefix) < $minLength) {
            throw new SearchException(
                errorCode: 'search.prefix_too_short',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['q' => ['search.prefix_too_short']],
            );
        }

        if (mb_strlen($prefix) > (int) config('search.keyword.max_length')) {
            throw new SearchException(
                errorCode: 'search.prefix_too_long',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['q' => ['search.prefix_too_long']],
            );
        }

        $cacheKey = 'search:suggestions:'.md5($locale.':'.$prefix);

        /** @var Collection<int, array{type: string, value: string, label: string|null}> $suggestions */
        $suggestions = Cache::remember(
            $cacheKey,
            (int) config('search.suggestions.cache_ttl'),
            fn (): Collection => $this->buildSuggestions($prefix, $locale, $maxResults),
        );

        return $suggestions;
    }

    /**
     * @return Collection<int, array{type: string, value: string, label: string|null}>
     */
    private function buildSuggestions(string $prefix, string $locale, int $maxResults): Collection
    {
        $escapedPrefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix);
        $likePrefix = $escapedPrefix.'%';

        $titleSuggestions = Listing::query()
            ->publiclyVisible()
            ->where('title', 'ILIKE', $likePrefix)
            ->orderBy('title')
            ->limit($maxResults)
            ->pluck('title')
            ->unique(fn (string $title) => mb_strtolower($title))
            ->values()
            ->map(fn (string $title): array => [
                'type' => 'listing_title',
                'value' => $title,
                'label' => null,
            ]);

        $remaining = max(0, $maxResults - $titleSuggestions->count());

        $categorySuggestions = collect();

        if ($remaining > 0) {
            $categorySuggestions = CategoryTranslation::query()
                ->select('category_translations.name')
                ->join('categories', 'categories.id', '=', 'category_translations.category_id')
                ->where('categories.status', CategoryStatus::Active)
                ->whereNull('categories.deleted_at')
                ->whereIn('category_translations.locale', [$locale, 'ar', 'en'])
                ->where('category_translations.name', 'ILIKE', $likePrefix)
                ->orderBy('category_translations.name')
                ->limit($remaining)
                ->pluck('name')
                ->unique(fn (string $name) => mb_strtolower($name))
                ->values()
                ->map(fn (string $name): array => [
                    'type' => 'category',
                    'value' => $name,
                    'label' => null,
                ]);
        }

        return $titleSuggestions
            ->merge($categorySuggestions)
            ->take($maxResults)
            ->values();
    }
}
