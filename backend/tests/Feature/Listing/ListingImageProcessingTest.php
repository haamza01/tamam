<?php

namespace Tests\Feature\Listing;

use App\Application\Audit\AuditLogService;
use App\Application\Listing\ListingImageProcessor;
use App\Application\Listing\ListingImageStorageService;
use App\Domain\Listing\Enums\ListingImageStatus;
use App\Jobs\ProcessListingImageJob;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ListingImageProcessingTest extends ListingTestCase
{
    public function test_delete_during_processing_does_not_recreate_row_or_variants(): void
    {
        Queue::fake();

        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $imageId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->json('data.image.id');

        $listing = Listing::query()->findOrFail($listingId);
        $image = ListingImage::query()->findOrFail($imageId);
        $storage = app(ListingImageStorageService::class);

        $image->forceFill(['status' => ListingImageStatus::Processing])->save();

        $this->withApiToken($token)
            ->deleteJson("/api/v1/listings/{$listingId}/images/{$imageId}")
            ->assertOk();

        (new ProcessListingImageJob($imageId))->handle(
            app(ListingImageProcessor::class),
            $storage,
            app(AuditLogService::class),
        );

        $this->assertDatabaseMissing('listing_images', ['id' => $imageId]);
        Storage::disk('public_assets')->assertMissing($storage->processedObjectKey($listing, $image));
        Storage::disk('public_assets')->assertMissing($storage->thumbnailObjectKey($listing, $image));
    }

    public function test_delete_after_display_write_cleans_partial_variants(): void
    {
        Queue::fake();

        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $imageId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->json('data.image.id');

        $listing = Listing::query()->findOrFail($listingId);
        $image = ListingImage::query()->findOrFail($imageId);
        $storage = app(ListingImageStorageService::class);
        $processedKey = $storage->processedObjectKey($listing, $image);
        $thumbnailKey = $storage->thumbnailObjectKey($listing, $image);

        $this->mock(ListingImageStorageService::class, function ($mock) use ($image, $processedKey, $thumbnailKey): void {
            $mock->shouldReceive('readSource')->andReturn(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
            $mock->shouldReceive('processedObjectKey')->andReturn($processedKey);
            $mock->shouldReceive('thumbnailObjectKey')->andReturn($thumbnailKey);
            $mock->shouldReceive('storeProcessed')
                ->twice()
                ->andReturnUsing(function (string $key, string $contents) use ($image, $processedKey, $thumbnailKey): void {
                    if ($key === $processedKey) {
                        Storage::disk('public_assets')->put($key, $contents);

                        return;
                    }

                    if ($key === $thumbnailKey) {
                        ListingImage::query()->whereKey($image->id)->delete();

                        throw new RuntimeException('Simulated delete during thumbnail write');
                    }
                });
            $mock->shouldReceive('deleteObjects')
                ->once()
                ->with([$processedKey])
                ->andReturnUsing(function (array $keys): void {
                    Storage::disk('public_assets')->delete($keys);
                });
        });

        try {
            (new ProcessListingImageJob($imageId))->handle(
                app(ListingImageProcessor::class),
                app(ListingImageStorageService::class),
                app(AuditLogService::class),
            );
        } catch (RuntimeException) {
            // simulated interruption
        }

        $this->assertDatabaseMissing('listing_images', ['id' => $imageId]);
        Storage::disk('public_assets')->assertMissing($processedKey);
    }

    public function test_retry_is_idempotent_when_image_already_ready(): void
    {
        Queue::fake();

        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $imageId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->json('data.image.id');

        $listing = Listing::query()->findOrFail($listingId);
        $image = ListingImage::query()->findOrFail($imageId);
        $storage = app(ListingImageStorageService::class);
        $processedKey = $storage->processedObjectKey($listing, $image);
        $thumbnailKey = $storage->thumbnailObjectKey($listing, $image);

        Storage::disk('public_assets')->put($processedKey, 'display');
        Storage::disk('public_assets')->put($thumbnailKey, 'thumb');

        $image->forceFill([
            'status' => ListingImageStatus::Ready,
            'processed_object_key' => $processedKey,
            'thumbnail_object_key' => $thumbnailKey,
            'processed_width' => 1,
            'processed_height' => 1,
            'original_object_key' => null,
        ])->save();

        (new ProcessListingImageJob($imageId))->handle(
            app(ListingImageProcessor::class),
            $storage,
            app(AuditLogService::class),
        );

        Storage::disk('public_assets')->assertExists($processedKey);
        $this->assertDatabaseHas('listing_images', [
            'id' => $imageId,
            'status' => ListingImageStatus::Ready->value,
        ]);
    }

    public function test_duplicate_worker_claim_is_ignored_while_processing(): void
    {
        Queue::fake();

        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $imageId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->json('data.image.id');

        ListingImage::query()->whereKey($imageId)->update([
            'status' => ListingImageStatus::Processing,
            'updated_at' => now(),
        ]);

        $job = new ProcessListingImageJob($imageId);
        $processor = app(ListingImageProcessor::class);
        $storage = app(ListingImageStorageService::class);
        $audit = app(AuditLogService::class);

        $job->handle($processor, $storage, $audit);

        $this->assertDatabaseHas('listing_images', [
            'id' => $imageId,
            'status' => ListingImageStatus::Processing->value,
        ]);
    }

    public function test_stale_processing_is_reclaimed(): void
    {
        Queue::fake();

        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $imageId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->json('data.image.id');

        ListingImage::query()->whereKey($imageId)->update([
            'status' => ListingImageStatus::Processing,
            'updated_at' => now()->subMinutes(10),
        ]);

        (new ProcessListingImageJob($imageId))->handle(
            app(ListingImageProcessor::class),
            app(ListingImageStorageService::class),
            app(AuditLogService::class),
        );

        $this->assertDatabaseHas('listing_images', [
            'id' => $imageId,
            'status' => ListingImageStatus::Ready->value,
        ]);
    }

    public function test_partial_thumbnail_failure_cleans_display_and_retains_source(): void
    {
        Queue::fake();

        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $imageId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->json('data.image.id');

        $listing = Listing::query()->findOrFail($listingId);
        $image = ListingImage::query()->findOrFail($imageId);
        $processedKey = app(ListingImageStorageService::class)->processedObjectKey($listing, $image);
        $thumbnailKey = app(ListingImageStorageService::class)->thumbnailObjectKey($listing, $image);
        $sourceKey = $image->original_object_key;

        $this->mock(ListingImageStorageService::class, function ($mock) use ($processedKey, $thumbnailKey): void {
            $mock->shouldReceive('readSource')->andReturn(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
            $mock->shouldReceive('processedObjectKey')->andReturn($processedKey);
            $mock->shouldReceive('thumbnailObjectKey')->andReturn($thumbnailKey);
            $mock->shouldReceive('storeProcessed')
                ->twice()
                ->andReturnUsing(function (string $key, string $contents) use ($processedKey, $thumbnailKey): void {
                    if ($key === $processedKey) {
                        Storage::disk('public_assets')->put($key, $contents);

                        return;
                    }

                    if ($key === $thumbnailKey) {
                        throw new RuntimeException('Simulated thumbnail write failure');
                    }
                });
            $mock->shouldReceive('deleteObjects')
                ->once()
                ->with([$processedKey])
                ->andReturnUsing(function (array $keys): void {
                    Storage::disk('public_assets')->delete($keys);
                });
        });

        try {
            (new ProcessListingImageJob($imageId))->handle(
                app(ListingImageProcessor::class),
                app(ListingImageStorageService::class),
                app(AuditLogService::class),
            );
        } catch (RuntimeException) {
            // expected rethrow for queue retry
        }

        $this->assertDatabaseHas('listing_images', [
            'id' => $imageId,
            'status' => ListingImageStatus::Failed->value,
            'processing_error_code' => 'listing.image_processing_failed',
        ]);
        Storage::disk('local')->assertExists($sourceKey);
        Storage::disk('public_assets')->assertMissing($processedKey);
    }

    public function test_job_implements_unique_until_processing_with_expiring_lock(): void
    {
        $job = new ProcessListingImageJob('00000000-0000-4000-8000-000000000001');

        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame(130, $job->uniqueFor);
        $this->assertSame('00000000-0000-4000-8000-000000000001', $job->uniqueId());
    }
}
