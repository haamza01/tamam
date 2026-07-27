<?php

namespace App\Http\Resources;

use App\Models\CategoryAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CategoryAttribute */
class CategoryAttributeResource extends JsonResource
{
    private string $locale = 'ar';

    public function withLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'label' => $this->translatedName($this->locale),
            'required' => $this->required,
            'unit' => $this->unit,
        ];

        if ($this->relationLoaded('options') && $this->options->isNotEmpty()) {
            $data['options'] = $this->options->map(fn ($option) => [
                'value' => $option->value,
                'label' => $option->translatedLabel($this->locale),
            ])->values()->all();
        }

        return $data;
    }
}
