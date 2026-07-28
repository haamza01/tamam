<?php

namespace App\Application\Favorite;

use App\Models\ListingStatistic;
use Illuminate\Support\Facades\DB;

class ListingStatisticsCounter
{
    public function incrementFavorites(string $listingId): void
    {
        $updated = DB::table('listing_statistics')
            ->where('listing_id', $listingId)
            ->update([
                'favorites_count' => DB::raw('favorites_count + 1'),
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            ListingStatistic::query()->create([
                'listing_id' => $listingId,
                'favorites_count' => 1,
            ]);
        }
    }

    public function decrementFavorites(string $listingId): void
    {
        DB::table('listing_statistics')
            ->where('listing_id', $listingId)
            ->update([
                'favorites_count' => DB::raw('GREATEST(0, favorites_count - 1)'),
                'updated_at' => now(),
            ]);
    }
}
