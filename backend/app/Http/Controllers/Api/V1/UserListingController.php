<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Listing\ListingService;
use App\Application\Shared\LocaleResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingCardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listingService,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $paginator = $this->listingService->paginateForOwner($request->user(), $request->query());

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
            message: 'Your listings retrieved successfully.',
        );
    }

    public function statistics(Request $request, string $id): JsonResponse
    {
        $listing = Listing::query()
            ->where('user_id', $request->user()->id)
            ->with('statistics')
            ->findOrFail($id);

        $this->authorize('view', $listing);

        return ApiResponse::success(
            data: [
                'statistics' => [
                    'views_count' => $listing->statistics?->views_count ?? 0,
                    'favorites_count' => $listing->statistics?->favorites_count ?? 0,
                    'messages_count' => $listing->statistics?->messages_count ?? 0,
                ],
            ],
            message: 'Listing statistics retrieved successfully.',
        );
    }
}
