<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasTranslations;
    use HasUuid;

    protected $fillable = [
        'code',
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

    /** @return HasMany<City, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /** @return HasMany<CountryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class);
    }
}
