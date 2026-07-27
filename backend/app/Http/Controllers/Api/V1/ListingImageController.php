<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Listing\ListingImageService;
use App\Application\Shared\LocaleResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\ReorderListingImagesRequest;
use App\Http\Requests\Listing\UploadListingImageRequest;
use App\Http\Resources\ListingImageResource;
use App\Http\Responses\ApiResponse;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListingImageController extends Controller
{
    public function __construct(
        private readonly ListingImageService $listingImages,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function store(UploadListingImageRequest $request, string $id): JsonResponse
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('update', $listing);

        $image = $this->listingImages->upload($request->user(), $listing, $request->file('image'));

        return ApiResponse::success(
            data: [
                'image' => (new ListingImageResource($image))->forOwner(true),
            ],
            message: 'Listing image uploaded successfully.',
            status: JsonResponse::HTTP_ACCEPTED,
        );
    }

    public function reorder(ReorderListingImagesRequest $request, string $id): JsonResponse
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('update', $listing);

        $images = $this->listingImages->reorder(
            $request->user(),
            $listing,
            $request->validated('image_ids'),
        );

        return ApiResponse::success(
            data: [
                'images' => $images
                    ->map(fn (ListingImage $image) => (new ListingImageResource($image))->forOwner(true))
                    ->values(),
            ],
            message: 'Listing images reordered successfully.',
        );
    }

    public function destroy(Request $request, string $id, string $imageId): JsonResponse
    {
        $listing = Listing::query()->findOrFail($id);
        $this->authorize('update', $listing);

        $image = ListingImage::query()
            ->where('listing_id', $listing->id)
            ->whereKey($imageId)
            ->firstOrFail();

        $this->listingImages->delete($request->user(), $listing, $image);

        return ApiResponse::success(message: 'Listing image deleted successfully.');
    }
}
