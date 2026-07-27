<?php

namespace App\Console\Commands;

use App\Application\Listing\ListingExpiryService;
use Illuminate\Console\Command;

class ExpireListingsCommand extends Command
{
    protected $signature = 'listings:expire';

    protected $description = 'Transition published listings past expires_at to expired (idempotent)';

    public function handle(ListingExpiryService $expiryService): int
    {
        $expired = $expiryService->expireDue();

        $this->info("Expired {$expired} listing(s).");

        return self::SUCCESS;
    }
}
