<?php

namespace App\Application\Category;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryListingCountService
{
    public function increment(string $categoryId): void
    {
        $this->adjust($categoryId, 1);
    }

    public function decrement(string $categoryId): void
    {
        $this->adjust($categoryId, -1);
    }

    public function recalculateAll(): int
    {
        $counts = DB::table('listings')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->select('category_id', DB::raw('COUNT(*) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        Category::query()->update(['listing_count' => 0]);

        foreach ($counts as $categoryId => $total) {
            Category::query()->whereKey($categoryId)->update(['listing_count' => (int) $total]);
        }

        return (int) $counts->sum();
    }

    private function adjust(string $categoryId, int $delta): void
    {
        DB::transaction(function () use ($categoryId, $delta): void {
            $category = Category::query()->lockForUpdate()->find($categoryId);

            if ($category === null) {
                return;
            }

            $next = max(0, $category->listing_count + $delta);
            $category->forceFill(['listing_count' => $next])->save();
        });
    }
}
