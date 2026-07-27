<?php

namespace App\Console\Commands;

use App\Application\Category\CategoryListingCountService;
use Illuminate\Console\Command;

class RecalculateCategoryListingCountsCommand extends Command
{
    protected $signature = 'listings:recalculate-category-counts';

    protected $description = 'Recalculate cached category listing counts from published listings';

    public function handle(CategoryListingCountService $service): int
    {
        $total = $service->recalculateAll();

        $this->info("Recalculated listing counts for {$total} published listings.");

        return self::SUCCESS;
    }
}
