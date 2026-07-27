<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'name' => $this->translatedName($this->locale),
            'icon' => $this->icon,
            'image' => $this->image,
            'sort_order' => $this->sort_order,
            'listing_count' => $this->listing_count,
        ];

        if ($this->relationLoaded('children')) {
            $data['children'] = $this->children
                ->map(fn (Category $child): array => (new self($child))->withLocale($this->locale)->toArray($request))
                ->values()
                ->all();
        }

        return $data;
    }
}
