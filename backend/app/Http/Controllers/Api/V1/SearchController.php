<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Search\PopularSearchService;
use App\Application\Search\SearchService;
use App\Application\Search\SearchSuggestionService;
use App\Application\Shared\LocaleResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchListingsRequest;
use App\Http\Requests\Search\SearchSuggestionsRequest;
use App\Http\Resources\ListingCardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SearchSuggestionService $suggestionService,
        private readonly PopularSearchService $popularSearchService,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function index(SearchListingsRequest $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $paginator = $this->searchService->search($request->filters());

        return ApiResponse::success(
            data: [
                'listings' => collect($paginator->items())
                    ->map(fn (Listing $listing) => (new ListingCardResource($listing))->withLocale($locale))
                    ->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            message: 'Search results retrieved successfully.',
        );
    }

    public function suggestions(SearchSuggestionsRequest $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $suggestions = $this->suggestionService->suggest($request->prefix(), $locale);

        return ApiResponse::success(
            data: [
                'suggestions' => $suggestions,
            ],
            message: 'Search suggestions retrieved successfully.',
        );
    }

    public function popular(Request $request): JsonResponse
    {
        $terms = $this->popularSearchService->popular();

        return ApiResponse::success(
            data: [
                'popular' => $terms,
            ],
            message: 'Popular searches retrieved successfully.',
        );
    }
}
