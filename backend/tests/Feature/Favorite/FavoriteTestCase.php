<?php

namespace Tests\Feature\Favorite;

use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\ListingStatistic;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Listing\ListingTestCase;

abstract class FavoriteTestCase extends ListingTestCase
{
    protected function moderator(): User
    {
        $moderator = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'phone_verified_at' => now(),
        ]);
        $moderator->assignRole('moderator');

        return $moderator;
    }

    protected function favoritePost(User $user, string $listingId): TestResponse
    {
        return $this->withApiToken($this->authenticate($user))
            ->postJson("/api/v1/listings/{$listingId}/favorite");
    }

    protected function favoriteDelete(User $user, string $listingId): TestResponse
    {
        return $this->withApiToken($this->authenticate($user))
            ->deleteJson("/api/v1/listings/{$listingId}/favorite");
    }

    protected function listFavorites(User $user, array $query = []): TestResponse
    {
        $url = '/api/v1/users/me/favorites';

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return $this->withApiToken($this->authenticate($user))->getJson($url);
    }

    protected function favoritesCount(string $listingId): int
    {
        return (int) ListingStatistic::query()
            ->where('listing_id', $listingId)
            ->value('favorites_count');
    }

    protected function markListingImageReady(Listing $listing): void
    {
        ListingImage::query()->create([
            'listing_id' => $listing->id,
            'sort_order' => 0,
            'status' => ListingImageStatus::Ready,
            'original_object_key' => null,
            'processed_object_key' => 'listings/'.$listing->id.'/ready/original.webp',
            'thumbnail_object_key' => 'listings/'.$listing->id.'/ready/thumb.webp',
        ]);
    }
}
