<?php

namespace App\Application\Category;

use App\Application\Catalog\CatalogCacheService;
use App\Domain\Category\Enums\CategoryStatus;
use App\Domain\Category\Exceptions\CategoryException;
use App\Models\Category;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class CategoryService
{
    public function __construct(
        private readonly CatalogCacheService $cache,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function flatActive(string $locale): Collection
    {
        $cacheKey = (string) config('catalog.cache_keys.categories_flat').':'.$locale;

        /** @var Collection<int, Category> $categories */
        $categories = $this->cache->remember($cacheKey, fn (): Collection => $this->loadFlatActive($locale));

        return $categories;
    }

    /**
     * @return Collection<int, Category>
     */
    public function treeActive(string $locale): Collection
    {
        $cacheKey = (string) config('catalog.cache_keys.categories_tree').':'.$locale;

        /** @var Collection<int, Category> $tree */
        $tree = $this->cache->remember($cacheKey, fn (): Collection => $this->buildTree($locale));

        return $tree;
    }

    public function attributesForActiveCategory(string $slug, string $locale): Collection
    {
        $category = $this->findActiveBySlug($slug, $locale);

        return $category->attributes()
            ->with(['translations' => fn ($query) => $query->whereIn('locale', [$locale, 'ar', 'en']), 'options.translations'])
            ->orderBy('sort_order')
            ->get();
    }

    public function findActiveBySlug(string $slug, string $locale): Category
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('status', CategoryStatus::Active)
            ->with(['translations' => fn ($query) => $query->whereIn('locale', [$locale, 'ar', 'en'])])
            ->first();

        if ($category === null) {
            throw new CategoryException(
                errorCode: 'category.not_found',
                message: 'The requested category was not found.',
                status: Response::HTTP_NOT_FOUND,
            );
        }

        return $category;
    }

    /**
     * @return Collection<int, Category>
     */
    private function loadFlatActive(string $locale): Collection
    {
        return Category::query()
            ->where('status', CategoryStatus::Active)
            ->orderBy('sort_order')
            ->with(['translations' => fn ($query) => $query->whereIn('locale', [$locale, 'ar', 'en'])])
            ->get();
    }

    /**
     * @return Collection<int, Category>
     */
    private function buildTree(string $locale): Collection
    {
        $categories = $this->loadFlatActive($locale);

        /** @var Collection<string, Category> $indexed */
        $indexed = $categories->keyBy('id');

        foreach ($categories as $category) {
            if ($category->parent_id !== null && $indexed->has($category->parent_id)) {
                $parent = $indexed->get($category->parent_id);
                $parent->setRelation('children', ($parent->relationLoaded('children')
                    ? $parent->getRelation('children')
                    : collect())->push($category));
            }
        }

        return $categories
            ->filter(fn (Category $category): bool => $category->parent_id === null
                || $indexed->has($category->parent_id))
            ->values()
            ->map(function (Category $category): Category {
                if (! $category->relationLoaded('children')) {
                    $category->setRelation('children', collect());
                }

                return $category;
            });
    }
}
