<?php

namespace Tests\Unit\Category;

use App\Application\Category\CategoryHierarchyValidator;
use App\Domain\Category\Enums\CategoryStatus;
use App\Domain\Category\Exceptions\CategoryException;
use App\Models\Category;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryHierarchyValidatorTest extends TestCase
{
    use RefreshDatabase;

    private CategoryHierarchyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSettingsSeeder::class);
        $this->validator = app(CategoryHierarchyValidator::class);
    }

    public function test_rejects_category_as_its_own_parent(): void
    {
        $category = $this->createCategory('cars');

        $this->expectException(CategoryException::class);

        try {
            $this->validator->validateParentAssignment($category, $category->id);
        } catch (CategoryException $exception) {
            $this->assertSame('category.cannot_be_own_parent', $exception->errors()['parent_id'][0]);

            throw $exception;
        }
    }

    public function test_rejects_descendant_as_parent(): void
    {
        $root = $this->createCategory('vehicles');
        $child = $this->createCategory('cars', $root->id);
        $grandchild = $this->createCategory('sedans', $child->id);

        $this->expectException(CategoryException::class);

        try {
            $this->validator->validateParentAssignment($root, $grandchild->id);
        } catch (CategoryException $exception) {
            $this->assertSame('category.circular_reference', $exception->errors()['parent_id'][0]);

            throw $exception;
        }
    }

    public function test_rejects_assignment_beyond_max_depth(): void
    {
        $levelOne = $this->createCategory('level-one');
        $levelTwo = $this->createCategory('level-two', $levelOne->id);
        $levelThree = $this->createCategory('level-three', $levelTwo->id);
        $candidateParent = $this->createCategory('candidate-parent');

        $this->expectException(CategoryException::class);

        try {
            $this->validator->validateParentAssignment($candidateParent, $levelThree->id);
        } catch (CategoryException $exception) {
            $this->assertSame('category.max_depth_exceeded', $exception->errors()['parent_id'][0]);

            throw $exception;
        }
    }

    public function test_allows_valid_parent_within_depth_limit(): void
    {
        $root = $this->createCategory('vehicles');
        $child = $this->createCategory('cars');

        $this->validator->validateParentAssignment($child, $root->id);

        $this->assertSame(1, $this->validator->depth($root));
        $this->assertSame(3, $this->validator->maxDepth());
    }

    private function createCategory(string $slug, ?string $parentId = null): Category
    {
        return Category::query()->create([
            'slug' => $slug,
            'parent_id' => $parentId,
            'sort_order' => 1,
            'status' => CategoryStatus::Active,
        ]);
    }
}
