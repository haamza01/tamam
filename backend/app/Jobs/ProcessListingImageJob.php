<?php

namespace App\Jobs;

use App\Application\Audit\AuditLogService;
use App\Application\Listing\ListingImageProcessor;
use App\Application\Listing\ListingImageStorageService;
use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessListingImageJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 120;

    public int $uniqueFor = 130;

    private const STALE_PROCESSING_MINUTES = 5;

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
        $context = $this->claimForProcessing();

        if ($context === null) {
            return;
        }

        /** @var Listing $listing */
        $listing = $context['listing'];
        /** @var ListingImage $image */
        $image = $context['image'];
        $sourceKey = $context['source_key'];

        $source = $storage->readSource($sourceKey);

        if ($source === null) {
            $this->markFailed($auditLog, 'listing.image_source_missing');

            return;
        }

        try {
            $processed = $processor->process($source);
        } catch (Throwable) {
            $this->markFailed($auditLog, 'listing.image_processing_failed');

            return;
        }

        if (! $this->imageExists($this->listingImageId)) {
            return;
        }

        $processedKey = $storage->processedObjectKey($listing, $image);
        $thumbnailKey = $storage->thumbnailObjectKey($listing, $image);
        $writtenKeys = [];

        try {
            $storage->storeProcessed($processedKey, $processed['processed']);
            $writtenKeys[] = $processedKey;
        } catch (Throwable $throwable) {
            $this->markFailed($auditLog, 'listing.image_processing_failed');

            throw $throwable;
        }

        if (! $this->imageExists($this->listingImageId)) {
            $storage->deleteObjects($writtenKeys);

            return;
        }

        try {
            $storage->storeProcessed($thumbnailKey, $processed['thumbnail']);
            $writtenKeys[] = $thumbnailKey;
        } catch (Throwable $throwable) {
            $storage->deleteObjects($writtenKeys);
            $this->markFailed($auditLog, 'listing.image_processing_failed');

            throw $throwable;
        }

        $committed = DB::transaction(function () use ($storage, $auditLog, $processed, $processedKey, $thumbnailKey, $writtenKeys): bool {
            $image = ListingImage::query()->lockForUpdate()->find($this->listingImageId);

            if ($image === null || $image->status !== ListingImageStatus::Processing) {
                $storage->deleteObjects($writtenKeys);

                return false;
            }

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

        if ($committed === false) {
            return;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed(app(AuditLogService::class), 'listing.image_processing_failed');
    }

    /**
     * @return array{image: ListingImage, listing: Listing, source_key: string|null}|null
     */
    private function claimForProcessing(): ?array
    {
        return DB::transaction(function (): ?array {
            $image = ListingImage::query()->lockForUpdate()->find($this->listingImageId);

            if ($image === null) {
                return null;
            }

            if ($image->status === ListingImageStatus::Ready) {
                return null;
            }

            if ($image->status === ListingImageStatus::Processing) {
                if ($image->updated_at !== null && $image->updated_at->isAfter(now()->subMinutes(self::STALE_PROCESSING_MINUTES))) {
                    return null;
                }
            } elseif (! $image->status->canTransitionTo(ListingImageStatus::Processing)) {
                return null;
            }

            $listing = $image->listing()->first();

            if ($listing === null) {
                return null;
            }

            $image->forceFill([
                'status' => ListingImageStatus::Processing,
                'processing_error_code' => null,
            ])->save();

            return [
                'image' => $image,
                'listing' => $listing,
                'source_key' => $image->original_object_key,
            ];
        });
    }

    private function imageExists(string $imageId): bool
    {
        return ListingImage::query()->whereKey($imageId)->exists();
    }

    private function markFailed(AuditLogService $auditLog, string $errorCode): void
    {
        DB::transaction(function () use ($auditLog, $errorCode): void {
            $image = ListingImage::query()->lockForUpdate()->find($this->listingImageId);

            if ($image === null || $image->status === ListingImageStatus::Ready) {
                return;
            }

            $image->forceFill([
                'status' => ListingImageStatus::Failed,
                'processing_error_code' => $errorCode,
            ])->save();

            $auditLog->log('listing.image.processing_failed', $image, null, [
                'listing_id' => $image->listing_id,
                'image_id' => $image->id,
                'error_code' => $errorCode,
            ]);
        });
    }
}
