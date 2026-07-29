<?php

namespace Tests\Feature\Favorite;

use App\Application\Favorite\FavoriteService;
use App\Models\Favorite;

class FavoriteHardeningTest extends FavoriteTestCase
{
    public function test_delete_when_favorite_never_existed_does_not_change_statistics(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->assertSame(0, $this->favoritesCount($listing->id));

        $this->favoriteDelete($buyer, $listing->id)
            ->assertOk()
            ->assertJsonPath('message', 'Listing removed from favorites successfully.');

        $this->assertSame(0, $this->favoritesCount($listing->id));
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_repeated_delete_decrements_statistics_only_once(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();
        $this->assertSame(1, $this->favoritesCount($listing->id));

        $this->favoriteDelete($buyer, $listing->id)->assertOk();
        $this->assertSame(0, $this->favoritesCount($listing->id));

        $this->favoriteDelete($buyer, $listing->id)->assertOk();
        $this->assertSame(0, $this->favoritesCount($listing->id));
    }

    public function test_delete_after_row_already_removed_does_not_decrement_statistics_again(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();

        $this->favoritePost($buyer, $listing->id)->assertCreated();
        $this->assertSame(1, $this->favoritesCount($listing->id));

        Favorite::query()
            ->where('user_id', $buyer->id)
            ->where('listing_id', $listing->id)
            ->delete();

        app(FavoriteService::class)->remove($buyer, $listing->id);

        $this->assertSame(1, $this->favoritesCount($listing->id));
        $this->assertSame(0, Favorite::query()->where('listing_id', $listing->id)->count());
    }

    public function test_service_remove_skips_statistics_when_no_matching_row(): void
    {
        $listing = $this->createPublishedListing();
        $buyer = $this->verifiedSeller();
        $service = app(FavoriteService::class);

        $service->add($buyer, $listing->id);
        $this->assertSame(1, $this->favoritesCount($listing->id));

        $service->remove($buyer, $listing->id);
        $this->assertSame(0, $this->favoritesCount($listing->id));

        $service->remove($buyer, $listing->id);
        $this->assertSame(0, $this->favoritesCount($listing->id));
    }
}
