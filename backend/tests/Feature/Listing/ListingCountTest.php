<?php

namespace Tests\Feature\Listing;

use App\Application\Category\CategoryListingCountService;
use App\Application\Listing\ListingStateMachine;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ListingCountTest extends ListingTestCase
{
    public function test_publish_increments_category_listing_count(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');
        $category = Category::query()->where('slug', 'sedans')->firstOrFail();

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token);
        app(ListingStateMachine::class)->approve(Listing::query()->findOrFail($listingId), $moderator);

        $this->assertSame(1, $category->fresh()->listing_count);
    }

    public function test_pause_decrements_category_listing_count(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');
        $category = Category::query()->where('slug', 'sedans')->firstOrFail();

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token);
        app(ListingStateMachine::class)->approve(Listing::query()->findOrFail($listingId), $moderator);

        $this->withApiToken($token)->postJson("/api/v1/listings/{$listingId}/pause")->assertOk();

        $this->assertSame(0, $category->fresh()->listing_count);
    }

    public function test_recalculate_command_rebuilds_counts(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');
        $category = Category::query()->where('slug', 'sedans')->firstOrFail();
        $category->forceFill(['listing_count' => 99])->save();

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token);
        app(ListingStateMachine::class)->approve(Listing::query()->findOrFail($listingId), $moderator);

        app(CategoryListingCountService::class)->recalculateAll();

        $this->assertSame(1, $category->fresh()->listing_count);
    }
}
