<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Listing\ListingService;
use App\Application\Listing\ListingStateMachine;
use App\Application\Shared\LocaleResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\CreateListingRequest;
use App\Http\Requests\Listing\UpdateListingRequest;
use App\Http\Resources\ListingCardResource;
use App\Http\Resources\ListingDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listingService,
        private readonly ListingStateMachine $stateMachine,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $paginator = $this->listingService->paginatePublic($request->query());

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
            message: 'Listings retrieved successfully.',
        );
    }

    public function latest(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);

        return ApiResponse::success(
            data: [
                'listings' => $this->listingService->latest()
                    ->map(fn (Listing $listing) => (new ListingCardResource($listing))->withLocale($locale))
                    ->values(),
            ],
            message: 'Latest listings retrieved successfully.',
        );
    }

    public function featured(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);

        return ApiResponse::success(
            data: [
                'listings' => $this->listingService->featured()
                    ->map(fn (Listing $listing) => (new ListingCardResource($listing))->withLocale($locale))
                    ->values(),
            ],
            message: 'Featured listings retrieved successfully.',
        );
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $listing = $this->listingService->findAccessible($id, $request->user());
        $this->authorize('view', $listing);

        $ownerView = $request->user() !== null && $listing->isOwnedBy($request->user());

        return ApiResponse::success(
            data: [
                'listing' => (new ListingDetailResource($listing))
                    ->withLocale($locale)
                    ->forOwner($ownerView),
            ],
            message: 'Listing retrieved successfully.',
        );
    }

    public function similar(Request $request, string $id): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $listing = $this->listingService->findPublic($id);

        return ApiResponse::success(
            data: [
                'listings' => $this->listingService->similar($listing)
                    ->map(fn (Listing $item) => (new ListingCardResource($item))->withLocale($locale))
                    ->values(),
            ],
            message: 'Similar listings retrieved successfully.',
        );
    }

    public function store(CreateListingRequest $request): JsonResponse
    {
        $this->authorize('create', Listing::class);

        $listing = $this->listingService->create($request->user(), $request->validated());
        $locale = $this->localeResolver->resolve($request);

        return ApiResponse::success(
            data: [
                'listing' => (new ListingDetailResource($listing))
                    ->withLocale($locale)
                    ->forOwner(true),
            ],
            message: 'Listing created successfully.',
            status: JsonResponse::HTTP_CREATED,
        );
    }

    public function update(UpdateListingRequest $request, string $id): JsonResponse
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('update', $listing);

        $updated = $this->listingService->update($request->user(), $listing, $request->validated());
        $locale = $this->localeResolver->resolve($request);

        return ApiResponse::success(
            data: [
                'listing' => (new ListingDetailResource($updated))
                    ->withLocale($locale)
                    ->forOwner(true),
            ],
            message: 'Listing updated successfully.',
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('delete', $listing);

        $this->stateMachine->softDelete($listing, $request->user());

        return ApiResponse::success(message: 'Listing deleted successfully.');
    }

    public function submit(Request $request, string $id): JsonResponse
    {
        $listing = Listing::query()->with(['attributeValues.categoryAttribute'])->findOrFail($id);
        $this->authorize('transition', $listing);

        $updated = $this->listingService->submitForReview($request->user(), $listing);
        $locale = $this->localeResolver->resolve($request);

        return ApiResponse::success(
            data: [
                'listing' => (new ListingDetailResource($updated))
                    ->withLocale($locale)
                    ->forOwner(true),
            ],
            message: 'Listing submitted for review successfully.',
        );
    }

    public function pause(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, fn ($listing, $user) => $this->stateMachine->pause($listing, $user));
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, fn ($listing, $user) => $this->stateMachine->activate($listing, $user));
    }

    public function markSold(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, fn ($listing, $user) => $this->stateMachine->markSold($listing, $user));
    }

    public function renew(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, fn ($listing, $user) => $this->stateMachine->renew($listing, $user));
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, fn ($listing, $user) => $this->stateMachine->archive($listing, $user));
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        return $this->transition($request, $id, fn ($listing, $user) => $this->stateMachine->restore($listing, $user));
    }

    private function transition(Request $request, string $id, callable $action): JsonResponse
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('transition', $listing);

        $updated = $action($listing, $request->user());
        $locale = $this->localeResolver->resolve($request);

        return ApiResponse::success(
            data: [
                'listing' => (new ListingDetailResource($updated))
                    ->withLocale($locale)
                    ->forOwner(true),
            ],
            message: 'Listing updated successfully.',
        );
    }
}
