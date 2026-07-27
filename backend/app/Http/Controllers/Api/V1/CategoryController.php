<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Category\CategoryService;
use App\Application\Shared\LocaleResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryAttributeResource;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $categories = $this->categoryService->flatActive($locale);

        return ApiResponse::success(
            data: [
                'categories' => $categories
                    ->map(fn ($category) => (new CategoryResource($category))->withLocale($locale))
                    ->values(),
            ],
            message: 'Categories retrieved successfully.',
        );
    }

    public function tree(Request $request): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $tree = $this->categoryService->treeActive($locale);

        return ApiResponse::success(
            data: [
                'categories' => $tree
                    ->map(fn ($category) => (new CategoryResource($category))->withLocale($locale))
                    ->values(),
            ],
            message: 'Category tree retrieved successfully.',
        );
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $category = $this->categoryService->findActiveBySlug($slug, $locale);

        return ApiResponse::success(
            data: [
                'category' => (new CategoryResource($category))->withLocale($locale),
            ],
            message: 'Category retrieved successfully.',
        );
    }

    public function attributes(Request $request, string $slug): JsonResponse
    {
        $locale = $this->localeResolver->resolve($request);
        $attributes = $this->categoryService->attributesForActiveCategory($slug, $locale);

        return ApiResponse::success(
            data: [
                'attributes' => $attributes
                    ->map(fn ($attribute) => (new CategoryAttributeResource($attribute))->withLocale($locale))
                    ->values(),
            ],
            message: 'Category attributes retrieved successfully.',
        );
    }
}
