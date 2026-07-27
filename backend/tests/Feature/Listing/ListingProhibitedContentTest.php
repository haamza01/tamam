<?php

namespace Tests\Feature\Listing;

class ListingProhibitedContentTest extends ListingTestCase
{
    public function test_create_rejects_prohibited_content(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload([
                'title' => 'Reliable sedan scam offer today',
                'description' => str_repeat('Well maintained vehicle with full service history and clean interior. ', 2),
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', 'listing.prohibited_content');
    }

    public function test_update_rejects_prohibited_content(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", [
                'description' => str_repeat('This vehicle is a scam and should not be trusted by buyers. ', 2),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.content.0', 'listing.prohibited_content');
    }
}
