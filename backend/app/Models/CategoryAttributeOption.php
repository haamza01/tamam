<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryAttributeOption extends Model
{
    use HasUuid;

    protected $fillable = [
        'category_attribute_id',
        'value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<CategoryAttribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class, 'category_attribute_id');
    }

    /** @return HasMany<CategoryAttributeOptionTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryAttributeOptionTranslation::class);
    }

    public function translatedLabel(string $locale, string $fallback = 'ar'): string
    {
        $translation = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();

        if ($translation !== null) {
            return $translation->label;
        }

        $fallbackTranslation = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $fallback)
            : $this->translations()->where('locale', $fallback)->first();

        return $fallbackTranslation?->label ?? $this->value;
    }
}
