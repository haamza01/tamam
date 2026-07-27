<?php

namespace App\Application\Search;

use App\Domain\Category\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryDescendantResolver
{
    /**
     * @return list<string>
     */
    public function idsIncludingSelf(string $categoryId): array
    {
        $cacheKey = (string) config('search.cache_keys.category_descendants').':'.$categoryId;

        /** @var list<string> $ids */
        $ids = Cache::remember($cacheKey, 3600, fn (): array => $this->resolve($categoryId));

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $categoryId): array
    {
        $categories = Category::query()
            ->where('status', CategoryStatus::Active)
            ->get(['id', 'parent_id']);

        if (! $categories->contains('id', $categoryId)) {
            return [$categoryId];
        }

        $childrenByParent = [];

        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                continue;
            }

            $childrenByParent[$category->parent_id][] = $category->id;
        }

        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return array_values(array_unique($ids));
    }
}
