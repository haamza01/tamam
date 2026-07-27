<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingAttributeValue extends Model
{
    use HasUuid;

    protected $fillable = [
        'listing_id',
        'category_attribute_id',
        'value_text',
        'value_number',
        'value_boolean',
        'value_date',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_boolean' => 'boolean',
            'value_date' => 'date',
            'value_json' => 'array',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<CategoryAttribute, $this> */
    public function categoryAttribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class);
    }
}
