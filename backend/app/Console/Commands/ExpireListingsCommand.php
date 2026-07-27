<?php

namespace App\Console\Commands;

use App\Application\Listing\ListingStateMachine;
use App\Domain\Listing\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Console\Command;

class ExpireListingsCommand extends Command
{
    protected $signature = 'listings:expire';

    protected $description = 'Transition published listings past expires_at to expired (idempotent)';

    public function handle(ListingStateMachine $stateMachine): int
    {
        $expired = 0;

        Listing::query()
            ->where('status', ListingStatus::Published)
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($listings) use ($stateMachine, &$expired): void {
                foreach ($listings as $listing) {
                    $stateMachine->expire($listing);
                    $expired++;
                }
            });

        $this->info("Expired {$expired} listing(s).");

        return self::SUCCESS;
    }
}
