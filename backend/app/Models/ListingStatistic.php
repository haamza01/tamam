<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingStatistic extends Model
{
    use HasUuid;

    protected $fillable = [
        'listing_id',
        'views_count',
        'favorites_count',
        'messages_count',
    ];

    protected function casts(): array
    {
        return [
            'views_count' => 'integer',
            'favorites_count' => 'integer',
            'messages_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
