<?php

namespace App\Application\Listing;

use App\Domain\Listing\Enums\ListingStatus;
use App\Models\Listing;

class ListingExpiryService
{
    public function __construct(
        private readonly ListingStateMachine $stateMachine,
    ) {}

    /**
     * Transition published listings past expires_at to expired.
     * Idempotent; safe to call on every public catalog/listing read and from the scheduler.
     */
    public function expireDue(): int
    {
        $expired = 0;

        Listing::query()
            ->where('status', ListingStatus::Published)
            ->whereNull('deleted_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($listings) use (&$expired): void {
                foreach ($listings as $listing) {
                    $this->stateMachine->expire($listing);
                    $expired++;
                }
            });

        return $expired;
    }
}
