<?php

namespace App\Application\Category;

use App\Application\Platform\PlatformSettingsService;
use App\Domain\Category\Exceptions\CategoryException;
use App\Models\Category;
use Symfony\Component\HttpFoundation\Response;

class CategoryHierarchyValidator
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function validateParentAssignment(Category $category, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($parentId === $category->id) {
            throw new CategoryException(
                errorCode: 'category.invalid_parent',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['parent_id' => ['category.cannot_be_own_parent']],
            );
        }

        $parent = Category::query()->find($parentId);

        if ($parent === null) {
            throw new CategoryException(
                errorCode: 'category.invalid_parent',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['parent_id' => ['category.parent_not_found']],
            );
        }

        if ($this->isDescendant($parent, $category->id)) {
            throw new CategoryException(
                errorCode: 'category.invalid_parent',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['parent_id' => ['category.circular_reference']],
            );
        }

        $parentDepth = $this->depth($parent);
        $maxDepth = $this->maxDepth();

        if ($parentDepth + 1 >= $maxDepth) {
            throw new CategoryException(
                errorCode: 'category.max_depth_exceeded',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['parent_id' => ['category.max_depth_exceeded']],
            );
        }
    }

    public function depth(Category $category): int
    {
        $depth = 1;
        $current = $category;

        while ($current->parent_id !== null) {
            $parent = Category::query()->find($current->parent_id);

            if ($parent === null) {
                break;
            }

            $depth++;
            $current = $parent;
        }

        return $depth;
    }

    public function maxDepth(): int
    {
        return max(1, $this->settings->getInt('category_max_depth', 3));
    }

    private function isDescendant(Category $candidate, string $ancestorId): bool
    {
        $current = $candidate;

        while ($current->parent_id !== null) {
            if ($current->parent_id === $ancestorId) {
                return true;
            }

            $parent = Category::query()->find($current->parent_id);

            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return false;
    }
}
