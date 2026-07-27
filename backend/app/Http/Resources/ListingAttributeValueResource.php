<?php

namespace App\Http\Resources;

use App\Models\ListingAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListingAttributeValue */
class ListingAttributeValueResource extends JsonResource
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
        $attribute = $this->categoryAttribute;

        return [
            'slug' => $attribute->slug,
            'label' => $attribute->translatedName($this->locale),
            'type' => $attribute->type->value,
            'value' => $this->presentedValue(),
        ];
    }

    private function presentedValue(): mixed
    {
        if ($this->value_json !== null) {
            return $this->value_json;
        }

        return $this->value_text
            ?? $this->value_number
            ?? $this->value_boolean
            ?? ($this->value_date?->format('Y-m-d'));
    }
}
