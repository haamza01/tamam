<?php

namespace App\Models;

use App\Domain\Category\Enums\AttributeType;
use App\Domain\Shared\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryAttribute extends Model
{
    use HasUuid;

    protected $fillable = [
        'category_id',
        'slug',
        'type',
        'required',
        'searchable',
        'filterable',
        'sort_order',
        'unit',
        'min_value',
        'max_value',
        'validation_rules',
    ];

    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'required' => 'boolean',
            'searchable' => 'boolean',
            'filterable' => 'boolean',
            'sort_order' => 'integer',
            'min_value' => 'decimal:4',
            'max_value' => 'decimal:4',
            'validation_rules' => 'array',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<CategoryAttributeTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryAttributeTranslation::class);
    }

    /** @return HasMany<CategoryAttributeOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(CategoryAttributeOption::class)->orderBy('sort_order');
    }

    public function translatedName(string $locale, string $fallback = 'ar'): string
    {
        $translation = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $locale)
            : $this->translations()->where('locale', $locale)->first();

        if ($translation !== null) {
            return $translation->name;
        }

        $fallbackTranslation = $this->relationLoaded('translations')
            ? $this->translations->firstWhere('locale', $fallback)
            : $this->translations()->where('locale', $fallback)->first();

        return $fallbackTranslation?->name ?? $this->slug;
    }
}
