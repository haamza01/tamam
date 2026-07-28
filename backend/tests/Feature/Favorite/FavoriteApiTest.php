<?php

namespace Tests\Feature\Favorite;

use App\Application\Favorite\FavoriteService;
use App\Application\Listing\ListingStateMachine;
use App\Domain\Category\Enums\CategoryStatus;
use App\Domain\Favorite\Exceptions\FavoriteException;
use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\District;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingStatistic;
use Illuminate\Support\Facades\DB;

class FavoriteApiTest extends FavoriteTestCase
{
    public function test_unauthenticated_favorite_endpoints_are_rejected(): void
    {
        $listing = $this->createPublishedListing();

        $this->asGuest()->postJson("/api/v1/listings/{$listing->id}/favorite")->assertUnauthorized();
        $this->asGuest()->deleteJson("/api/v1/listings/{$listing->id}/favorite")->assertUnauthorized();
        $this->asGuest()->getJson('/api/v1/users/me/favorites')->assertUnauthorized();
    }

    public function test_published_public_listing_can_be_favorited(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.favorite.listing_id', $listing->id)
            ->assertJsonPath('message', 'Listing added to favorites successfully.');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
        ]);
        $this->assertSame(1, $this->favoritesCount($listing->id));
    }

    public function test_own_listing_cannot_be_favorited(): void
    {
        $listing = $this->createPublishedListing();

        $this->favoritePost($listing->user, $listing->id)
            ->assertForbidden()
            ->assertJsonPath('errors.listing.0', 'favorite.own_listing');

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $listing->user_id,
            'listing_id' => $listing->id,
        ]);
        $this->assertSame(0, $this->favoritesCount($listing->id));
    }

    public function test_draft_listing_cannot_be_favorited(): void
    {
        $owner = $this->verifiedSeller();
        $buyer = $this->verifiedSeller();
        $token = $this->authenticate($owner);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->favoritePost($buyer, $listingId)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_pending_review_listing_cannot_be_favorited(): void
    {
        $owner = $this->verifiedSeller();
        $buyer = $this->verifiedSeller();
        $token = $this->authenticate($owner);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload())
            ->json('data.listing.id');

        $this->submitListingForReview($listingId, $token);

        $this->favoritePost($buyer, $listingId)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_rejected_listing_cannot_be_favorited(): void
    {
        $owner = $this->verifiedSeller();
        $buyer = $this->verifiedSeller();
        $moderator = $this->moderator();
        $token = $this->authenticate($owner);

        $listing = Listing::query()->findOrFail(
            $this->withApiToken($token)->postJson('/api/v1/listings', $this->validListingPayload())->json('data.listing.id')
        );

        $this->submitListingForReview($listing->id, $token);
        app(ListingStateMachine::class)->reject($listing->fresh(), $moderator, 'Incomplete');

        $this->favoritePost($buyer, $listing->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_archived_listing_cannot_be_favorited(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->withApiToken($this->authenticate($listing->user))
            ->postJson("/api/v1/listings/{$listing->id}/archive")
            ->assertOk();

        $this->favoritePost($buyer, $listing->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_paused_listing_cannot_be_favorited(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->withApiToken($this->authenticate($listing->user))
            ->postJson("/api/v1/listings/{$listing->id}/pause")
            ->assertOk();

        $this->favoritePost($buyer, $listing->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_expired_listing_cannot_be_favorited(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $listing->update(['expires_at' => now()->subMinute()]);

        $this->favoritePost($buyer, $listing->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_soft_deleted_listing_cannot_be_favorited(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->withApiToken($this->authenticate($listing->user))
            ->deleteJson("/api/v1/listings/{$listing->id}")
            ->assertOk();

        $this->favoritePost($buyer, $listing->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_inactive_category_listing_cannot_be_favorited(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        Category::query()->whereKey($listing->category_id)->update(['status' => CategoryStatus::Hidden]);

        $this->favoritePost($buyer, $listing->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_inactive_city_listing_cannot_be_favorited(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        City::query()->whereKey($listing->city_id)->update(['is_active' => false]);

        $this->favoritePost($buyer, $listing->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_inactive_district_listing_cannot_be_favorited(): void
    {
        $city = City::query()->where('slug', 'doha')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->where('is_active', true)->firstOrFail();

        $owner = $this->verifiedSeller();
        $moderator = $this->moderator();
        $buyer = $this->verifiedSeller();
        $token = $this->authenticate($owner);

        $listingId = $this->withApiToken($token)
            ->postJson('/api/v1/listings', $this->validListingPayload(['district_id' => $district->id]))
            ->json('data.listing.id');

        $this->publishListing($listingId, $owner, $moderator);
        $district->update(['is_active' => false]);

        $this->favoritePost($buyer, $listingId)
            ->assertNotFound()
            ->assertJsonPath('message', 'The requested listing was not found.');
    }

    public function test_duplicate_post_returns_conflict_and_does_not_increment_count(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $this->favoritePost($buyer, $listing->id)
            ->assertStatus(409)
            ->assertJsonPath('errors.favorite.0', 'favorite.already_exists');

        $this->assertSame(1, Favorite::query()->where('listing_id', $listing->id)->count());
        $this->assertSame(1, $this->favoritesCount($listing->id));
    }

    public function test_existing_favorite_can_be_removed(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $this->favoriteDelete($buyer, $listing->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Listing removed from favorites successfully.');

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
        ]);
        $this->assertSame(0, $this->favoritesCount($listing->id));
    }

    public function test_repeated_delete_is_idempotent_and_does_not_decrement_below_zero(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();
        $this->favoriteDelete($buyer, $listing->id)->assertOk();
        $this->favoriteDelete($buyer, $listing->id)->assertOk();

        $this->assertSame(0, $this->favoritesCount($listing->id));
    }

    public function test_another_user_cannot_remove_someone_elses_favorite_count(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();
        $other = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $this->favoriteDelete($other, $listing->id)->assertOk();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
        ]);
        $this->assertSame(1, $this->favoritesCount($listing->id));
    }

    public function test_user_sees_only_their_own_favorites(): void
    {
        $listingA = $this->createPublishedListing();
        $listingB = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();
        $other = $this->verifiedSeller();

        $this->favoritePost($buyer, $listingA->id)->assertCreated();
        $this->favoritePost($other, $listingB->id)->assertCreated();

        $ids = collect($this->listFavorites($buyer)->json('data.listings'))->pluck('id');

        $this->assertTrue($ids->contains($listingA->id));
        $this->assertFalse($ids->contains($listingB->id));
    }

    public function test_favorites_are_ordered_most_recently_favorited_first(): void
    {
        $listingA = $this->createPublishedListing();
        $listingB = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listingA->id)->assertCreated();

        Favorite::query()
            ->where('user_id', $buyer->id)
            ->where('listing_id', $listingA->id)
            ->update(['created_at' => now()->subHour()]);

        $this->favoritePost($buyer, $listingB->id)->assertCreated();

        $ids = collect($this->listFavorites($buyer)->json('data.listings'))->pluck('id');

        $this->assertSame([$listingB->id, $listingA->id], $ids->all());
    }

    public function test_favorites_list_supports_pagination(): void
    {
        $buyer = $this->verifiedSeller();
        $this->createPublishedListing();
        $listingB = $this->createPublishedListing();

        $this->favoritePost($buyer, $this->createPublishedListing()->id)->assertCreated();
        $this->favoritePost($buyer, $listingB->id)->assertCreated();

        $this->listFavorites($buyer, ['per_page' => 1, 'page' => 1])
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonCount(1, 'data.listings');
    }

    public function test_unavailable_favorited_listings_are_excluded_from_list(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $this->withApiToken($this->authenticate($listing->user))
            ->postJson("/api/v1/listings/{$listing->id}/pause")
            ->assertOk();

        $this->listFavorites($buyer)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_expired_favorited_listings_are_excluded_from_list(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();
        $listing->update(['expires_at' => now()->subMinute()]);

        $this->listFavorites($buyer)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_soft_deleted_favorited_listings_are_excluded_from_list(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $this->withApiToken($this->authenticate($listing->user))
            ->deleteJson("/api/v1/listings/{$listing->id}")
            ->assertOk();

        $this->listFavorites($buyer)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
    }

    public function test_favorites_list_exposes_only_ready_cover_images(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        ListingImage::query()->where('listing_id', $listing->id)->update([
            'status' => ListingImageStatus::Processing,
            'processed_object_key' => null,
            'thumbnail_object_key' => null,
        ]);

        ListingImage::query()->create([
            'listing_id' => $listing->id,
            'sort_order' => 1,
            'status' => ListingImageStatus::Ready,
            'processed_object_key' => 'listings/'.$listing->id.'/ready/original.webp',
            'thumbnail_object_key' => 'listings/'.$listing->id.'/ready/thumb.webp',
        ]);

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $response = $this->listFavorites($buyer)->assertOk();
        $cover = $response->json('data.listings.0.cover_image');

        $this->assertIsString($cover);
        $this->assertStringContainsString('ready/thumb.webp', $cover);
    }

    public function test_favorites_list_does_not_leak_internal_fields(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $card = $this->listFavorites($buyer)->json('data.listings.0');

        $this->assertArrayNotHasKey('user_id', $card);
        $this->assertArrayNotHasKey('status', $card);
        $this->assertArrayNotHasKey('moderation', $card);
        $this->assertArrayNotHasKey('phone', $card);
        $this->assertArrayNotHasKey('email', $card);
        $this->assertArrayHasKey('id', $card);
        $this->assertArrayHasKey('title', $card);
    }

    public function test_statistics_count_matches_persisted_favorites_after_operations(): void
    {
        $listing = $this->createPublishedListing();
        $buyerA = $this->verifiedSeller();
        $buyerB = $this->verifiedSeller();

        $this->favoritePost($buyerA, $listing->id)->assertCreated();
        $this->favoritePost($buyerB, $listing->id)->assertCreated();

        $this->assertSame(2, Favorite::query()->where('listing_id', $listing->id)->count());
        $this->assertSame(2, $this->favoritesCount($listing->id));

        $this->favoriteDelete($buyerA, $listing->id)->assertOk();

        $this->assertSame(1, Favorite::query()->where('listing_id', $listing->id)->count());
        $this->assertSame(1, $this->favoritesCount($listing->id));
    }

    public function test_multiple_users_increment_statistics_independently(): void
    {
        $listing = $this->createPublishedListing();

        $this->favoritePost($this->verifiedSeller(), $listing->id)->assertCreated();
        $this->favoritePost($this->verifiedSeller(), $listing->id)->assertCreated();
        $this->favoritePost($this->verifiedSeller(), $listing->id)->assertCreated();

        $this->assertSame(3, $this->favoritesCount($listing->id));
    }

    public function test_deleting_one_users_favorite_leaves_others_intact(): void
    {
        $listing = $this->createPublishedListing();
        $buyerA = $this->verifiedSeller();
        $buyerB = $this->verifiedSeller();

        $this->favoritePost($buyerA, $listing->id)->assertCreated();
        $this->favoritePost($buyerB, $listing->id)->assertCreated();

        $this->favoriteDelete($buyerA, $listing->id)->assertOk();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $buyerB->id,
            'listing_id' => $listing->id,
        ]);
        $this->assertSame(1, $this->favoritesCount($listing->id));
    }

    public function test_unique_constraint_prevents_duplicate_rows_on_race(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for unique-constraint race test.');
        }

        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        Favorite::query()->create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
        ]);

        $this->favoritePost($buyer, $listing->id)
            ->assertStatus(409)
            ->assertJsonPath('errors.favorite.0', 'favorite.already_exists');

        $this->assertSame(1, Favorite::query()->where('listing_id', $listing->id)->count());
    }

    public function test_missing_statistics_row_is_created_on_first_favorite(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        ListingStatistic::query()->where('listing_id', $listing->id)->delete();

        $this->favoritePost($buyer, $listing->id)->assertCreated();

        $this->assertSame(1, $this->favoritesCount($listing->id));
    }

    public function test_favorites_list_avoids_n_plus_one_queries(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL required for query-count assertion.');
        }

        $buyer = $this->verifiedSeller();

        foreach (range(1, 3) as $_) {
            $listing = $this->createPublishedListing();
            $this->favoritePost($buyer, $listing->id)->assertCreated();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->listFavorites($buyer)->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(15, $queryCount);
    }

    public function test_favorite_service_duplicate_add_throws_without_double_increment(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();
        $service = app(FavoriteService::class);

        $service->add($buyer, $listing->id);

        try {
            $service->add($buyer, $listing->id);
            $this->fail('Expected duplicate favorite conflict.');
        } catch (FavoriteException $exception) {
            $this->assertSame('favorite.already_exists', $exception->errorCode());
        }

        $this->assertSame(1, $this->favoritesCount($listing->id));
    }
}
