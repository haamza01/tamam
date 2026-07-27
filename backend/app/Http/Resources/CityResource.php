<?php

namespace App\Http\Resources;

use App\Models\City;
use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin City */
class CityResource extends JsonResource
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
            'country_id' => $this->country_id,
            'slug' => $this->slug,
            'name' => $this->translatedName($this->locale),
            'sort_order' => $this->sort_order,
        ];

        if ($this->relationLoaded('districts')) {
            $data['districts'] = $this->districts
                ->map(fn (District $district): array => (new DistrictResource($district))->withLocale($this->locale)->toArray($request))
                ->values()
                ->all();
        }

        return $data;
    }
}
