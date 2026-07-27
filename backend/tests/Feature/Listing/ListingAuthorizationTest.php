<?php

namespace Tests\Feature\Listing;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ListingAuthorizationTest extends ListingTestCase
{
    public function test_unauthenticated_user_cannot_create_listing(): void
    {
        $this->postJson('/api/v1/listings', $this->validListingPayload())
            ->assertUnauthorized();
    }

    public function test_unverified_phone_cannot_create_listing(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'phone_verified_at' => null,
        ]);
        $user->assignRole('user');
        $token = $this->authenticate($user);

        $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->assertForbidden()
            ->assertJsonPath('errors.auth.0', 'auth.phone_not_verified');
    }

    public function test_non_owner_cannot_update_listing(): void
    {
        $owner = $this->verifiedSeller();
        $other = $this->verifiedSeller(['phone' => '+97455999999']);
        $ownerToken = $this->authenticate($owner);
        $this->authenticate($other);

        $listingId = $this->withApiToken($ownerToken)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->actingAsApi($other)
            ->putJson("/api/v1/listings/{$listingId}", ['title' => 'Hijacked listing title'])
            ->assertForbidden();
    }

    public function test_draft_listing_is_hidden_from_public_detail(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->asGuest()
            ->getJson("/api/v1/listings/{$listingId}")
            ->assertNotFound();
    }
}
