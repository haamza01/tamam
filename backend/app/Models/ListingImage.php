<?php

namespace App\Models;

use App\Domain\Listing\Enums\ListingImageStatus;
use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingImage extends Model
{
    use HasUuid;

    protected $fillable = [
        'listing_id',
        'original_object_key',
        'processed_object_key',
        'thumbnail_object_key',
        'mime_type',
        'original_width',
        'original_height',
        'processed_width',
        'processed_height',
        'file_size',
        'sort_order',
        'status',
        'processing_error_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => ListingImageStatus::class,
            'original_width' => 'integer',
            'original_height' => 'integer',
            'processed_width' => 'integer',
            'processed_height' => 'integer',
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function isReady(): bool
    {
        return $this->status === ListingImageStatus::Ready;
    }
}
