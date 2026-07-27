<?php

namespace Tests\Feature\Listing;

use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ListingImageCleanupTest extends ListingTestCase
{
    private function createDraftListing(): Listing
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        return Listing::query()->findOrFail($listingId);
    }

    public function test_dry_run_reports_orphans_without_deleting_valid_objects(): void
    {
        $listing = $this->createDraftListing();
        $image = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'sort_order' => 0,
            'status' => ListingImageStatus::Ready,
            'original_object_key' => null,
            'processed_object_key' => 'listings/'.$listing->id.'/ready/original.webp',
            'thumbnail_object_key' => 'listings/'.$listing->id.'/ready/thumb.webp',
        ]);

        $validSource = 'listings/'.$listing->id.'/'.$image->id.'/source';
        Storage::disk('local')->put($validSource, 'source');

        $orphanSource = 'listings/'.$listing->id.'/00000000-0000-4000-8000-000000000099/source';
        Storage::disk('local')->put($orphanSource, 'orphan');
        touch(Storage::disk('local')->path($orphanSource), now()->subHours(25)->getTimestamp());

        Artisan::call('listings:cleanup-orphan-images', ['--dry-run' => true]);

        Storage::disk('local')->assertExists($validSource);
        Storage::disk('local')->assertExists($orphanSource);
        $this->assertDatabaseHas('listing_images', ['id' => $image->id]);
        $this->assertStringContainsString('Would delete orphan source object', Artisan::output());
    }

    public function test_cleanup_removes_orphan_source_with_no_db_row(): void
    {
        $listing = $this->createDraftListing();
        $orphanSource = 'listings/'.$listing->id.'/00000000-0000-4000-8000-000000000099/source';
        Storage::disk('local')->put($orphanSource, 'orphan');
        touch(Storage::disk('local')->path($orphanSource), now()->subHours(25)->getTimestamp());

        Artisan::call('listings:cleanup-orphan-images');

        Storage::disk('local')->assertMissing($orphanSource);
    }

    public function test_cleanup_does_not_delete_recent_orphan_sources(): void
    {
        $listing = $this->createDraftListing();
        $orphanSource = 'listings/'.$listing->id.'/00000000-0000-4000-8000-000000000099/source';
        Storage::disk('local')->put($orphanSource, 'recent orphan');

        Artisan::call('listings:cleanup-orphan-images');

        Storage::disk('local')->assertExists($orphanSource);
    }

    public function test_cleanup_removes_stale_pending_rows_and_storage(): void
    {
        $listing = $this->createDraftListing();
        $sourceKey = 'listings/'.$listing->id.'/00000000-0000-4000-8000-000000000010/source';
        Storage::disk('local')->put($sourceKey, 'stale');

        $image = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'sort_order' => 0,
            'status' => ListingImageStatus::Pending,
            'original_object_key' => $sourceKey,
        ]);

        ListingImage::query()->whereKey($image->id)->update([
            'updated_at' => Carbon::now()->subHours(25),
        ]);

        Artisan::call('listings:cleanup-orphan-images');

        $this->assertDatabaseMissing('listing_images', ['id' => $image->id]);
        Storage::disk('local')->assertMissing($sourceKey);
    }

    public function test_cleanup_skips_active_processing_rows(): void
    {
        $listing = $this->createDraftListing();
        $sourceKey = 'listings/'.$listing->id.'/00000000-0000-4000-8000-000000000011/source';
        Storage::disk('local')->put($sourceKey, 'processing');

        $image = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'sort_order' => 0,
            'status' => ListingImageStatus::Processing,
            'original_object_key' => $sourceKey,
        ]);

        Artisan::call('listings:cleanup-orphan-images');

        $this->assertDatabaseHas('listing_images', ['id' => $image->id]);
        Storage::disk('local')->assertExists($sourceKey);
    }
}
