<?php

namespace Tests\Feature\Listing;

use App\Application\Listing\ListingStateMachine;
use App\Domain\Listing\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ListingLifecycleTest extends ListingTestCase
{
    public function test_submit_moves_draft_to_pending_review(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token)
            ->assertJsonPath('data.listing.status', 'pending_review');
    }

    public function test_invalid_transition_returns_conflict(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/pause")
            ->assertStatus(409)
            ->assertJsonPath('errors.listing.0', 'listing.invalid_transition');
    }

    public function test_published_listing_can_be_paused_and_activated(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token);
        app(ListingStateMachine::class)->approve(Listing::query()->findOrFail($listingId), $moderator);

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/pause")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'paused');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/activate")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'published')
            ->assertJsonPath('data.listing.published_at', fn ($value) => $value !== null);
    }

    public function test_rejected_listing_can_be_resubmitted(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        $listing = Listing::query()->findOrFail(
            $this->withApiToken($token)->postJson('/api/v1/listings', $this->validListingPayload())->json('data.listing.id')
        );

        $this->submitListingForReview($listing->id, $token);
        app(ListingStateMachine::class)->reject($listing->fresh(), $moderator, 'Incomplete details');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listing->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'pending_review');
    }

    public function test_delete_soft_deletes_listing(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->deleteJson("/api/v1/listings/{$listingId}")
            ->assertOk();

        $this->assertSoftDeleted('listings', ['id' => $listingId]);
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => ListingStatus::Deleted->value]);
    }
}
