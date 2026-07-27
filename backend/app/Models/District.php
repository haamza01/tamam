<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasTranslations;
    use HasUuid;

    protected $fillable = [
        'city_id',
        'slug',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<DistrictTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(DistrictTranslation::class);
    }
}
