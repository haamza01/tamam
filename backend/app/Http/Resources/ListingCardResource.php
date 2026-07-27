<?php

namespace App\Http\Resources;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingCardResource extends JsonResource
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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'price' => $this->price,
            'price_type' => $this->price_type->value,
            'currency' => $this->currency,
            'condition' => $this->condition?->value,
            'cover_image' => null,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'slug' => $this->city->slug,
                'name' => $this->city->translatedName($this->locale),
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->translatedName($this->locale),
            ]),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
