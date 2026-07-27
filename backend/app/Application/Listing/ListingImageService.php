<?php

namespace App\Application\Listing;

use App\Application\Audit\AuditLogService;
use App\Domain\Listing\Enums\ListingImageStatus;
use App\Domain\Listing\Exceptions\ListingException;
use App\Jobs\ProcessListingImageJob;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ListingImageService
{
    public function __construct(
        private readonly ListingImageValidator $validator,
        private readonly ListingImageStorageService $storage,
        private readonly AuditLogService $auditLog,
    ) {}

    public function upload(User $user, Listing $listing, UploadedFile $file): ListingImage
    {
        $this->assertCanManageImages($user, $listing);
        $this->validator->validateUpload($file);

        $imageInfo = getimagesize($file->getRealPath());
        $image = null;
        $storedKey = null;

        try {
            $image = DB::transaction(function () use ($listing, $file, $imageInfo, &$storedKey): ListingImage {
                $lockedListing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
                $this->assertImageLimit($lockedListing);

                $latestSortOrder = ListingImage::query()
                    ->where('listing_id', $lockedListing->id)
                    ->orderByDesc('sort_order')
                    ->lockForUpdate()
                    ->value('sort_order');

                $nextSortOrder = $latestSortOrder === null ? 0 : ((int) $latestSortOrder + 1);

                $image = ListingImage::query()->create([
                    'listing_id' => $lockedListing->id,
                    'mime_type' => $file->getMimeType(),
                    'original_width' => $imageInfo[0] ?? null,
                    'original_height' => $imageInfo[1] ?? null,
                    'file_size' => $file->getSize(),
                    'sort_order' => $nextSortOrder,
                    'status' => ListingImageStatus::Pending,
                ]);

                $storedKey = $this->storage->storeSource(
                    $lockedListing,
                    $image,
                    file_get_contents($file->getRealPath()),
                    (string) $file->getMimeType(),
                );

                $image->forceFill(['original_object_key' => $storedKey])->save();

                return $image;
            });
        } catch (\Throwable $exception) {
            if ($storedKey !== null) {
                $this->storage->deleteObjects([$storedKey]);
            }

            throw $exception;
        }

        ProcessListingImageJob::dispatch($image->id)->afterCommit();

        $this->auditLog->log('listing.image.uploaded', $image, $user, [
            'listing_id' => $listing->id,
            'image_id' => $image->id,
            'sort_order' => $image->sort_order,
        ]);

        return $image->fresh();
    }

    /**
     * @param  list<string>  $imageIds
     */
    public function reorder(User $user, Listing $listing, array $imageIds): Collection
    {
        $this->assertCanManageImages($user, $listing);

        $existingIds = ListingImage::query()
            ->where('listing_id', $listing->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        if (count($imageIds) !== count($existingIds)) {
            throw $this->conflictError('listing.image_reorder_incomplete', 'image_ids');
        }

        if (count(array_unique($imageIds)) !== count($imageIds)) {
            throw $this->conflictError('listing.image_reorder_duplicate', 'image_ids');
        }

        $foreign = array_diff($imageIds, $existingIds);

        if ($foreign !== []) {
            throw $this->conflictError('listing.image_not_found', 'image_ids');
        }

        DB::transaction(function () use ($listing, $imageIds): void {
            Listing::query()->lockForUpdate()->findOrFail($listing->id);

            ListingImage::query()
                ->where('listing_id', $listing->id)
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->get();

            $temporaryOffset = 1000;

            foreach (array_values($imageIds) as $position => $imageId) {
                ListingImage::query()
                    ->where('listing_id', $listing->id)
                    ->whereKey($imageId)
                    ->update(['sort_order' => $temporaryOffset + $position]);
            }

            foreach (array_values($imageIds) as $position => $imageId) {
                ListingImage::query()
                    ->where('listing_id', $listing->id)
                    ->whereKey($imageId)
                    ->update(['sort_order' => $position]);
            }
        });

        $this->auditLog->log('listing.images.reordered', $listing, $user, [
            'listing_id' => $listing->id,
            'image_ids' => $imageIds,
        ]);

        return $this->imagesForListing($listing);
    }

    public function delete(User $user, Listing $listing, ListingImage $image): void
    {
        $this->assertCanManageImages($user, $listing);

        if ($image->listing_id !== $listing->id) {
            throw $this->notFound();
        }

        $objectKeys = [
            $image->original_object_key,
            $image->processed_object_key,
            $image->thumbnail_object_key,
        ];

        DB::transaction(function () use ($listing, $image): void {
            Listing::query()->lockForUpdate()->findOrFail($listing->id);

            $locked = ListingImage::query()
                ->where('listing_id', $listing->id)
                ->lockForUpdate()
                ->find($image->id);

            if ($locked === null) {
                return;
            }

            $deletedSortOrder = $locked->sort_order;
            $locked->delete();

            ListingImage::query()
                ->where('listing_id', $listing->id)
                ->where('sort_order', '>', $deletedSortOrder)
                ->decrement('sort_order');
        });

        $this->storage->deleteObjects($objectKeys);

        $this->auditLog->log('listing.image.deleted', $listing, $user, [
            'listing_id' => $listing->id,
            'image_id' => $image->id,
        ]);
    }

    public function assertSubmitMinimum(Listing $listing): void
    {
        $readyCount = ListingImage::query()
            ->where('listing_id', $listing->id)
            ->where('status', ListingImageStatus::Ready)
            ->count();

        if ($readyCount < (int) config('media.listing.min_ready_for_submit')) {
            throw new ListingException(
                errorCode: 'listing.image_required',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['images' => ['listing.image_required']],
            );
        }
    }

    /**
     * @return Collection<int, ListingImage>
     */
    public function imagesForListing(Listing $listing): Collection
    {
        return ListingImage::query()
            ->where('listing_id', $listing->id)
            ->orderBy('sort_order')
            ->get();
    }

    private function assertCanManageImages(User $user, Listing $listing): void
    {
        if (! $listing->isOwnedBy($user)) {
            throw $this->notEditable();
        }

        if (! $listing->isEditableByOwner()) {
            throw $this->notEditable();
        }
    }

    private function assertImageLimit(Listing $listing): void
    {
        $count = ListingImage::query()->where('listing_id', $listing->id)->count();

        if ($count >= (int) config('media.listing.max_count')) {
            throw new ListingException(
                errorCode: 'listing.image_limit_reached',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['images' => ['listing.image_limit_reached']],
            );
        }
    }

    private function notFound(): ListingException
    {
        return new ListingException(
            errorCode: 'listing.image_not_found',
            message: 'The requested listing image was not found.',
            status: Response::HTTP_NOT_FOUND,
        );
    }

    private function notEditable(): ListingException
    {
        return new ListingException(
            errorCode: 'listing.not_editable',
            message: 'This listing cannot be edited in its current state.',
            status: Response::HTTP_FORBIDDEN,
        );
    }

    private function conflictError(string $code, string $field): ListingException
    {
        return new ListingException(
            errorCode: $code,
            message: 'Validation failed.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            errors: [$field => [$code]],
        );
    }
}
