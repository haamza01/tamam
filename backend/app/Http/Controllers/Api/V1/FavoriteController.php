<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Favorite\FavoriteService;
use App\Application\Shared\LocaleResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\ListingCardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Favorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function store(Request $request, string $id): JsonResponse
    {
        $favorite = $this->favoriteService->add($request->user(), $id);

        return ApiResponse::success(
            data: [
                'favorite' => [
                    'listing_id' => $favorite->listing_id,
                    'created_at' => $favorite->created_at?->toIso8601String(),
                ],
            ],
            message: 'Listing added to favorites successfully.',
            status: Response::HTTP_CREATED,
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->favoriteService->remove($request->user(), $id);

        return ApiResponse::success(
            message: 'Listing removed from favorites successfully.',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $paginator = $this->favoriteService->paginateForUser($request->user(), $request->query());

        return ApiResponse::success(
            data: [
                'listings' => collect($paginator->items())
                    ->map(fn (Favorite $favorite) => (new ListingCardResource($favorite->listing))->withLocale($locale))
                    ->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            message: 'Favorites retrieved successfully.',
        );
    }
}
