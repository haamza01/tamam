<?php

namespace Tests\Feature\Listing;

use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\ListingImage;
use Illuminate\Http\UploadedFile;

class ListingImageTest extends ListingTestCase
{
    public function test_owner_can_upload_listing_image(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $response = $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/images", [
                'image' => $this->makePngUpload(),
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.image.status', 'ready')
            ->assertJsonStructure(['data' => ['image' => ['id', 'url', 'thumbnail_url', 'sort_order']]]);

        $this->assertDatabaseHas('listing_images', [
            'listing_id' => $listingId,
            'status' => ListingImageStatus::Ready->value,
            'sort_order' => 0,
        ]);
    }

    public function test_submit_requires_at_least_one_ready_image(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/submit")
            ->assertUnprocessable()
            ->assertJsonPath('errors.images.0', 'listing.image_required');
    }

    public function test_submit_succeeds_with_ready_image(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->assertStatus(202);

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/submit")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'pending_review');
    }

    public function test_non_owner_cannot_upload_image(): void
    {
        $owner = $this->verifiedSeller();
        $other = $this->verifiedSeller(['phone' => '+97455999998']);
        $ownerToken = $this->authenticate($owner);

        $listingId = $this->withApiToken($ownerToken)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->actingAsApi($other)
            ->postJson("/api/v1/listings/{$listingId}/images", [
                'image' => $this->makePngUpload(),
            ])
            ->assertForbidden();
    }

    public function test_rejects_svg_upload(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $svg = UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/images", ['image' => $svg])
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'listing.image_invalid_type');
    }

    public function test_owner_can_reorder_images(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $firstId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload('first.png'),
        ])->json('data.image.id');

        $secondId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload('second.png'),
        ])->json('data.image.id');

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}/images/reorder", [
                'image_ids' => [$secondId, $firstId],
            ])
            ->assertOk()
            ->assertJsonPath('data.images.0.id', $secondId)
            ->assertJsonPath('data.images.1.id', $firstId);
    }

    public function test_owner_can_delete_image(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $imageId = $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/images", [
            'image' => $this->makePngUpload(),
        ])->json('data.image.id');

        $this->withApiToken($token)
            ->deleteJson("/api/v1/listings/{$listingId}/images/{$imageId}")
            ->assertOk();

        $this->assertDatabaseMissing('listing_images', ['id' => $imageId]);
    }

    public function test_public_listing_detail_shows_only_ready_images(): void
    {
        $listing = $this->createPublishedListing();

        ListingImage::query()->create([
            'listing_id' => $listing->id,
            'sort_order' => 1,
            'status' => ListingImageStatus::Failed,
            'processing_error_code' => 'listing.image_processing_failed',
        ]);

        $this->asGuest()
            ->getJson("/api/v1/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.listing.images')
            ->assertJsonMissingPath('data.listing.images.0.processing_error_code');
    }
}
