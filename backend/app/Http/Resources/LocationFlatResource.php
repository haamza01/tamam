<?php

namespace App\Http\Resources;

use App\Models\City;
use App\Models\Country;
use App\Models\District;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class LocationFlatResource extends JsonResource
{
    /**
     * @param  array{
     *     countries: Collection<int, Country>,
     *     cities: Collection<int, City>,
     *     districts: Collection<int, District>
     * }  $resource
     */
    public function __construct($resource, private readonly string $locale = 'ar')
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'countries' => $this->resource['countries']
                ->map(fn (Country $country): array => (new CountryResource($country))->withLocale($this->locale)->toArray($request))
                ->values()
                ->all(),
            'cities' => $this->resource['cities']
                ->map(fn (City $city): array => (new CityResource($city))->withLocale($this->locale)->toArray($request))
                ->values()
                ->all(),
            'districts' => $this->resource['districts']
                ->map(fn (District $district): array => (new DistrictResource($district))->withLocale($this->locale)->toArray($request))
                ->values()
                ->all(),
        ];
    }
}
