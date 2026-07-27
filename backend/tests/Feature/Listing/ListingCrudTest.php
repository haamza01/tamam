<?php

namespace Tests\Feature\Listing;

use App\Application\Listing\ListingStateMachine;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ListingCrudTest extends ListingTestCase
{
    public function test_verified_user_can_create_draft_listing(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $response = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.listing.status', 'draft')
            ->assertJsonPath('data.listing.images', []);

        $this->assertDatabaseHas('listings', [
            'user_id' => $user->id,
            'status' => 'draft',
        ]);
    }

    public function test_create_rejects_non_leaf_category(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);
        $vehicles = Category::query()->where('slug', 'vehicles')->firstOrFail();

        $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload(['category_id' => $vehicles->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_id.0', 'category.must_be_leaf');
    }

    public function test_partial_update_preserves_omitted_fields(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", ['title' => 'Updated sedan listing title'])
            ->assertOk()
            ->assertJsonPath('data.listing.title', 'Updated sedan listing title')
            ->assertJsonPath('data.listing.price', '45000.00');
    }

    public function test_protected_fields_are_rejected_on_update(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", ['status' => 'published'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'The status field is prohibited.');
    }

    public function test_public_index_shows_only_published_listings(): void
    {
        $owner = $this->verifiedSeller();
        $token = $this->authenticate($owner);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token);

        $listing = Listing::query()->findOrFail($listingId);
        app(ListingStateMachine::class)->approve($listing, $moderator);

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonPath('data.listings.0.id', $listingId);

        $draftId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload(['title' => 'Another sedan listing title']))
            ->json('data.listing.id');

        $ids = collect($this->getJson('/api/v1/listings')->json('data.listings'))->pluck('id');
        $this->assertTrue($ids->contains($listingId));
        $this->assertFalse($ids->contains($draftId));
    }

    public function test_public_detail_hides_owner_workflow_fields(): void
    {
        $owner = $this->verifiedSeller();
        $token = $this->authenticate($owner);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token);
        app(ListingStateMachine::class)->approve(Listing::query()->findOrFail($listingId), $moderator);

        $this->asGuest()
            ->getJson("/api/v1/listings/{$listingId}")
            ->assertOk()
            ->assertJsonMissingPath('data.listing.status')
            ->assertJsonMissingPath('data.listing.moderation_notes')
            ->assertJsonMissingPath('data.listing.seller.email')
            ->assertJsonMissingPath('data.listing.seller.phone');
    }
}
