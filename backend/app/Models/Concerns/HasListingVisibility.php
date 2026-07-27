<?php

namespace App\Models\Concerns;

use App\Domain\Listing\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Builder;

trait HasListingVisibility
{
    /**
     * Published, not soft-deleted, and not past expires_at.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', ListingStatus::Published)
            ->whereNull('deleted_at')
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function isPubliclyVisibleNow(): bool
    {
        if ($this->status !== ListingStatus::Published || $this->deleted_at !== null) {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public function countsForCategoryTotal(): bool
    {
        return $this->isPubliclyVisibleNow();
    }

    public function isSoftDeleted(): bool
    {
        return $this->status === ListingStatus::Deleted && $this->deleted_at !== null;
    }

    public function isPastExpiry(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(now());
    }
}
