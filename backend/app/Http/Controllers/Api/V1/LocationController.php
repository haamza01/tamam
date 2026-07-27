<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Location\LocationService;
use App\Application\Shared\LocaleResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Http\Resources\LocationFlatResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(
        private readonly LocationService $locationService,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $locations = $this->locationService->flatActive($locale);

        return ApiResponse::success(
            data: [
                'locations' => new LocationFlatResource($locations, $locale),
            ],
            message: 'Locations retrieved successfully.',
        );
    }

    public function tree(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $tree = $this->locationService->treeActive($locale);

        return ApiResponse::success(
            data: [
                'locations' => $tree
                    ->map(fn ($country) => (new CountryResource($country))->withLocale($locale))
                    ->values(),
            ],
            message: 'Location tree retrieved successfully.',
        );
    }
}
