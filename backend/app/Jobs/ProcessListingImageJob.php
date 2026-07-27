<?php

namespace App\Jobs;

use App\Application\Audit\AuditLogService;
use App\Application\Listing\ListingImageProcessor;
use App\Application\Listing\ListingImageStorageService;
use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\ListingImage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessListingImageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 120;

    public function __construct(
        public readonly string $listingImageId,
    ) {
        $this->onQueue('media');
    }

    public function uniqueId(): string
    {
        return $this->listingImageId;
    }

    public function handle(
        ListingImageProcessor $processor,
        ListingImageStorageService $storage,
        AuditLogService $auditLog,
    ): void {
        $result = DB::transaction(function () use ($processor, $storage, $auditLog): bool {
            $image = ListingImage::query()->lockForUpdate()->find($this->listingImageId);

            if ($image === null) {
                return false;
            }

            if ($image->status === ListingImageStatus::Ready) {
                return false;
            }

            if ($image->status === ListingImageStatus::Processing) {
                if ($image->updated_at !== null && $image->updated_at->isAfter(now()->subMinutes(5))) {
                    return false;
                }
            } elseif (! $image->status->canTransitionTo(ListingImageStatus::Processing)) {
                return false;
            }

            $image->forceFill([
                'status' => ListingImageStatus::Processing,
                'processing_error_code' => null,
            ])->save();

            $listing = $image->listing()->first();

            if ($listing === null) {
                return false;
            }

            $source = $storage->readSource($image->original_object_key);

            if ($source === null) {
                $image->forceFill([
                    'status' => ListingImageStatus::Failed,
                    'processing_error_code' => 'listing.image_source_missing',
                ])->save();

                $auditLog->log('listing.image.processing_failed', $image, null, [
                    'listing_id' => $image->listing_id,
                    'image_id' => $image->id,
                    'error_code' => 'listing.image_source_missing',
                ]);

                return false;
            }

            try {
                $processed = $processor->process($source);
            } catch (Throwable) {
                $image->forceFill([
                    'status' => ListingImageStatus::Failed,
                    'processing_error_code' => 'listing.image_processing_failed',
                ])->save();

                $auditLog->log('listing.image.processing_failed', $image, null, [
                    'listing_id' => $image->listing_id,
                    'image_id' => $image->id,
                    'error_code' => 'listing.image_processing_failed',
                ]);

                return false;
            }

            $processedKey = $storage->processedObjectKey($listing, $image);
            $thumbnailKey = $storage->thumbnailObjectKey($listing, $image);

            $storage->storeProcessed($processedKey, $processed['processed']);
            $storage->storeProcessed($thumbnailKey, $processed['thumbnail']);

            $image->forceFill([
                'status' => ListingImageStatus::Ready,
                'processed_object_key' => $processedKey,
                'thumbnail_object_key' => $thumbnailKey,
                'processed_width' => $processed['processed_width'],
                'processed_height' => $processed['processed_height'],
                'processing_error_code' => null,
            ])->save();

            $storage->deleteObjects([$image->original_object_key]);
            $image->forceFill(['original_object_key' => null])->save();

            $auditLog->log('listing.image.processing_succeeded', $image, null, [
                'listing_id' => $image->listing_id,
                'image_id' => $image->id,
            ]);

            return true;
        });

        if ($result === false) {
            return;
        }
    }

    public function failed(Throwable $exception): void
    {
        $image = ListingImage::query()->find($this->listingImageId);

        if ($image === null || $image->status === ListingImageStatus::Ready) {
            return;
        }

        $image->forceFill([
            'status' => ListingImageStatus::Failed,
            'processing_error_code' => 'listing.image_processing_failed',
        ])->save();
    }
}
