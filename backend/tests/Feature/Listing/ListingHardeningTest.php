<?php

namespace Tests\Feature\Listing;

use App\Application\Category\CategoryListingCountService;
use App\Application\Listing\ListingStateMachine;
use App\Application\Platform\PlatformSettingsService;
use App\Domain\Listing\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class ListingHardeningTest extends ListingTestCase
{
    public function test_delete_sets_status_and_deleted_at_together(): void
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
        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'status' => ListingStatus::Deleted->value,
        ]);
    }

    public function test_repeated_delete_is_idempotent(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)->deleteJson("/api/v1/listings/{$listingId}")->assertOk();
        $this->withApiToken($token)->deleteJson("/api/v1/listings/{$listingId}")->assertOk();
    }

    public function test_deleted_listing_is_hidden_from_owner_index_and_public_detail(): void
    {
        $listing = $this->createPublishedListing();
        $token = $this->authenticate($listing->user);

        $this->withApiToken($token)->deleteJson("/api/v1/listings/{$listing->id}")->assertOk();

        $this->asGuest()->getJson("/api/v1/listings/{$listing->id}")->assertNotFound();

        $ownerIds = collect(
            $this->withApiToken($token)->getJson('/api/v1/users/me/listings')->json('data.listings')
        )->pluck('id');

        $this->assertFalse($ownerIds->contains($listing->id));
    }

    public function test_restore_moves_archived_listing_to_draft(): void
    {
        $listing = $this->createPublishedListing();
        $token = $this->authenticate($listing->user);

        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/archive")->assertOk();

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listing->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'draft');
    }

    public function test_repeated_restore_is_idempotent(): void
    {
        $listing = $this->createPublishedListing();
        $token = $this->authenticate($listing->user);

        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/archive")->assertOk();
        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/restore")->assertOk();
        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/restore")->assertOk()
            ->assertJsonPath('data.listing.status', 'draft');
    }

    public function test_expired_published_listing_is_excluded_from_public_index(): void
    {
        $listing = $this->createPublishedListing();
        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();

        $ids = collect($this->getJson('/api/v1/listings')->json('data.listings'))->pluck('id');
        $this->assertFalse($ids->contains($listing->id));
    }

    public function test_expired_published_listing_returns_not_found_for_public_detail(): void
    {
        $listing = $this->createPublishedListing();
        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();

        $this->asGuest()->getJson("/api/v1/listings/{$listing->id}")->assertNotFound();
    }

    public function test_owner_can_view_expired_published_listing_with_workflow_fields(): void
    {
        $listing = $this->createPublishedListing();
        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();
        $token = $this->authenticate($listing->user);

        $this->withApiToken($token)
            ->getJson("/api/v1/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'published');
    }

    public function test_expire_command_transitions_published_listings_past_expiry(): void
    {
        $listing = $this->createPublishedListing();
        $category = Category::query()->findOrFail($listing->category_id);
        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();

        Artisan::call('listings:expire');

        $this->assertSame(ListingStatus::Expired, $listing->fresh()->status);
        $this->assertSame(0, $category->fresh()->listing_count);
    }

    public function test_expire_command_is_idempotent(): void
    {
        $listing = $this->createPublishedListing();
        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();

        Artisan::call('listings:expire');
        Artisan::call('listings:expire');

        $this->assertSame(ListingStatus::Expired, $listing->fresh()->status);
    }

    public function test_recalculate_excludes_expired_and_soft_deleted_listings(): void
    {
        $listing = $this->createPublishedListing();
        $category = Category::query()->findOrFail($listing->category_id);
        $category->forceFill(['listing_count' => 99])->save();

        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();

        app(CategoryListingCountService::class)->recalculateAll();
        $this->assertSame(0, $category->fresh()->listing_count);

        $listing->forceFill([
            'status' => ListingStatus::Published,
            'expires_at' => now()->addDay(),
        ])->save();

        app(CategoryListingCountService::class)->recalculateAll();
        $this->assertSame(1, $category->fresh()->listing_count);

        app(ListingStateMachine::class)->softDelete($listing->fresh(), $listing->user);
        app(CategoryListingCountService::class)->recalculateAll();
        $this->assertSame(0, $category->fresh()->listing_count);
    }

    public function test_recalculate_resets_stale_non_zero_counts_for_empty_categories(): void
    {
        $category = Category::query()->where('slug', 'phones')->firstOrFail();
        $category->forceFill(['listing_count' => 42])->save();

        app(CategoryListingCountService::class)->recalculateAll();

        $this->assertSame(0, $category->fresh()->listing_count);
    }

    public function test_update_with_matching_version_succeeds_and_increments_version(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $version = Listing::query()->findOrFail($listingId)->version;

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", [
                'title' => 'Updated sedan listing title',
                'version' => $version,
            ])
            ->assertOk()
            ->assertJsonPath('data.listing.version', $version + 1);
    }

    public function test_update_with_stale_version_returns_conflict(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", [
                'title' => 'First update to sedan listing',
                'version' => 1,
            ])
            ->assertOk();

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", [
                'title' => 'Second update to sedan listing',
                'version' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.version.0', 'listing.version_conflict');
    }

    public function test_update_without_version_allows_optimistic_concurrency_bypass(): void
    {
        $user = $this->verifiedSeller();
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", ['title' => 'First update to sedan listing'])
            ->assertOk();

        $this->withApiToken($token)
            ->putJson("/api/v1/listings/{$listingId}", ['title' => 'Second update to sedan listing'])
            ->assertOk()
            ->assertJsonPath('data.listing.title', 'Second update to sedan listing');
    }

    public function test_trusted_seller_auto_publishes_when_setting_enabled(): void
    {
        app(PlatformSettingsService::class)->set('auto_publish_for_trusted_users', true);

        $user = $this->verifiedSeller(['trusted_seller' => true]);
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/submit")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'published');
    }

    public function test_trusted_seller_auto_publish_respects_disabled_setting(): void
    {
        app(PlatformSettingsService::class)->set('auto_publish_for_trusted_users', false);

        $user = $this->verifiedSeller(['trusted_seller' => true]);
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/submit")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'pending_review');
    }

    public function test_non_trusted_user_cannot_auto_publish_even_when_setting_enabled(): void
    {
        app(PlatformSettingsService::class)->set('auto_publish_for_trusted_users', true);

        $user = $this->verifiedSeller(['trusted_seller' => false]);
        $token = $this->authenticate($user);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listingId}/submit")
            ->assertOk()
            ->assertJsonPath('data.listing.status', 'pending_review');
    }

    public function test_delete_decrements_category_listing_count(): void
    {
        $listing = $this->createPublishedListing();
        $category = Category::query()->findOrFail($listing->category_id);
        $token = $this->authenticate($listing->user);

        $this->assertSame(1, $category->fresh()->listing_count);

        $this->withApiToken($token)->deleteJson("/api/v1/listings/{$listing->id}")->assertOk();

        $this->assertSame(0, $category->fresh()->listing_count);
    }

    public function test_category_api_reconciles_listing_count_for_expired_published_listings(): void
    {
        $listing = $this->createPublishedListing();
        $category = Category::query()->findOrFail($listing->category_id);

        $this->assertSame(1, $category->fresh()->listing_count);

        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();

        $this->getJson('/api/v1/categories?locale=en')
            ->assertOk();

        $this->assertSame(0, $category->fresh()->listing_count);
        $this->assertSame(ListingStatus::Expired, $listing->fresh()->status);
    }

    public function test_public_listing_index_reconciles_expired_listings_before_query(): void
    {
        $listing = $this->createPublishedListing();
        $listing->forceFill([
            'published_at' => now()->subDays(31),
            'expires_at' => now()->subDay(),
        ])->save();

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->assertSame(ListingStatus::Expired, $listing->fresh()->status);
    }

    public function test_archived_listing_cannot_activate_directly_to_published(): void
    {
        $listing = $this->createPublishedListing();
        $token = $this->authenticate($listing->user);

        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/archive")->assertOk();

        $this->withApiToken($token)
            ->postJson("/api/v1/listings/{$listing->id}/activate")
            ->assertStatus(409)
            ->assertJsonPath('errors.listing.0', 'listing.invalid_transition');
    }

    public function test_archived_listing_republishes_through_restore_and_submit(): void
    {
        $listing = $this->createPublishedListing();
        $token = $this->authenticate($listing->user);
        $moderator = User::factory()->create(['password' => Hash::make('Password123!'), 'phone_verified_at' => now()]);
        $moderator->assignRole('moderator');

        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/archive")->assertOk();
        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/restore")->assertOk()
            ->assertJsonPath('data.listing.status', 'draft');

        $this->withApiToken($token)->postJson("/api/v1/listings/{$listing->id}/submit")->assertOk()
            ->assertJsonPath('data.listing.status', 'pending_review');

        app(ListingStateMachine::class)->approve(Listing::query()->findOrFail($listing->id), $moderator);

        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
    }

    public function test_featured_listings_route_is_not_registered_in_phase_1e(): void
    {
        $this->getJson('/api/v1/listings/featured')->assertNotFound();
    }
}
