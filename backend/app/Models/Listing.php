<?php

namespace App\Models;

use App\Domain\Listing\Enums\ListingCondition;
use App\Domain\Listing\Enums\ListingStatus;
use App\Domain\Listing\Enums\PriceType;
use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'city_id',
        'district_id',
        'title',
        'slug',
        'description',
        'price',
        'price_type',
        'currency',
        'condition',
        'status',
        'rejection_reason',
        'moderation_notes',
        'latitude',
        'longitude',
        'contact_preferences',
        'featured',
        'expires_at',
        'published_at',
        'sold_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_type' => PriceType::class,
            'condition' => ListingCondition::class,
            'status' => ListingStatus::class,
            'contact_preferences' => 'array',
            'featured' => 'boolean',
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'sold_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** @return HasMany<ListingAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ListingAttributeValue::class);
    }

    /** @return HasOne<ListingStatistic, $this> */
    public function statistics(): HasOne
    {
        return $this->hasOne(ListingStatistic::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isEditableByOwner(): bool
    {
        return in_array($this->status, [
            ListingStatus::Draft,
            ListingStatus::Rejected,
            ListingStatus::Paused,
            ListingStatus::Published,
        ], true);
    }
}
