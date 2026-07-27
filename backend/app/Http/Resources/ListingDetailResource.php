<?php

namespace App\Http\Resources;

use App\Domain\Listing\Enums\ListingImageStatus;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Listing */
class ListingDetailResource extends JsonResource
{
    private string $locale = 'ar';

    private bool $ownerView = false;

    public function withLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function forOwner(bool $ownerView = true): self
    {
        $this->ownerView = $ownerView;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'price_type' => $this->price_type->value,
            'currency' => $this->currency,
            'condition' => $this->condition?->value,
            'contact_preferences' => $this->contact_preferences,
            'images' => $this->relationLoaded('images') ? $this->mapImages() : [],
            'attributes' => $this->whenLoaded(
                'attributeValues',
                fn () => $this->attributeValues
                    ->map(fn ($value) => (new ListingAttributeValueResource($value))->withLocale($this->locale))
                    ->values(),
            ),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'slug' => $this->category->slug,
                'name' => $this->category->translatedName($this->locale),
            ]),
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'slug' => $this->city->slug,
                'name' => $this->city->translatedName($this->locale),
            ]),
            'district' => $this->whenLoaded('district', fn () => $this->district ? [
                'id' => $this->district->id,
                'slug' => $this->district->slug,
                'name' => $this->district->translatedName($this->locale),
            ] : null),
            'seller' => $this->whenLoaded('user', fn () => new PublicSellerResource($this->user)),
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->ownerView) {
            $data['status'] = $this->status->value;
            $data['rejection_reason'] = $this->rejection_reason;
            $data['version'] = $this->version;
        }

        return $data;
    }

    /**
     * @return Collection<int, ListingImageResource>
     */
    private function mapImages()
    {
        $images = $this->ownerView
            ? $this->images
            : $this->images->filter(fn ($image) => $image->status === ListingImageStatus::Ready);

        return $images
            ->map(fn ($image) => (new ListingImageResource($image))->forOwner($this->ownerView))
            ->values();
    }
}
