<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTranslations
{
    public function translationFor(string $locale): ?Model
    {
        if ($this->relationLoaded('translations')) {
            return $this->translations->firstWhere('locale', $locale);
        }

        return $this->translations()->where('locale', $locale)->first();
    }

    public function translatedName(string $locale, string $fallbackLocale = 'ar'): string
    {
        $translation = $this->translationFor($locale)
            ?? $this->translationFor($fallbackLocale);

        return $translation?->name ?? '';
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function scopeWithLocale(Builder $query, string $locale): Builder
    {
        return $query->with(['translations' => fn (HasMany $relation): HasMany => $relation->where('locale', $locale)]);
    }
}
