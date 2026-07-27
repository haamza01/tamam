<?php

namespace Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SeedsTranslations
{
    /**
     * @param  array{ar: string, en: string, description?: array{ar?: string|null, en?: string|null}}  $translations
     */
    protected function seedTranslations(Model $model, array $translations, string $relation = 'translations'): void
    {
        foreach (['ar', 'en'] as $locale) {
            $payload = [
                'locale' => $locale,
                'name' => $translations[$locale],
            ];

            if (array_key_exists('description', $translations)) {
                $payload['description'] = $translations['description'][$locale] ?? null;
            }

            $model->{$relation}()->create($payload);
        }
    }
}
