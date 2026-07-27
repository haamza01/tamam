<?php

namespace App\Http\Resources;

use App\Models\City;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Country */
class CountryResource extends JsonResource
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
            'code' => $this->code,
            'slug' => $this->slug,
            'name' => $this->translatedName($this->locale),
            'sort_order' => $this->sort_order,
        ];

        if ($this->relationLoaded('cities')) {
            $data['cities'] = $this->cities
                ->map(fn (City $city): array => (new CityResource($city))->withLocale($this->locale)->toArray($request))
                ->values()
                ->all();
        }

        return $data;
    }
}
