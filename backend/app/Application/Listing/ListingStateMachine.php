<?php

namespace App\Application\Listing;

use App\Application\Audit\AuditLogService;
use App\Application\Catalog\CatalogCacheService;
use App\Application\Category\CategoryListingCountService;
use App\Application\Platform\PlatformSettingsService;
use App\Domain\Listing\Enums\ListingStatus;
use App\Domain\Listing\Exceptions\ListingException;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ListingStateMachine
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly CategoryListingCountService $listingCounts,
        private readonly AuditLogService $auditLog,
        private readonly CatalogCacheService $catalogCache,
    ) {}

    public function submit(Listing $listing, User $actor): Listing
    {
        if (! in_array($listing->status, [ListingStatus::Draft, ListingStatus::Rejected], true)) {
            throw $this->invalidTransition();
        }

        $target = $this->resolveSubmitTarget($actor);

        return $this->transition($listing, $target, $actor, 'listing.submitted');
    }

    public function approve(Listing $listing, User $actor): Listing
    {
        $this->assertModerator($actor);

        return $this->transition($listing, ListingStatus::Published, $actor, 'listing.published', ['via' => 'moderation']);
    }

    public function reject(Listing $listing, User $actor, string $reason): Listing
    {
        $this->assertModerator($actor);

        return DB::transaction(function () use ($listing, $actor, $reason): Listing {
            $previous = $listing->status;
            $this->applyCountDelta($listing, $previous, ListingStatus::Rejected);

            $listing->forceFill([
                'status' => ListingStatus::Rejected,
                'rejection_reason' => $reason,
            ])->save();

            $this->auditLog->log('listing.rejected', $listing, $actor, [
                'from' => $previous->value,
                'to' => ListingStatus::Rejected->value,
            ]);

            return $listing->fresh(['category', 'city', 'district', 'attributeValues.categoryAttribute', 'statistics', 'user']);
        });
    }

    public function pause(Listing $listing, User $actor): Listing
    {
        return $this->transition($listing, ListingStatus::Paused, $actor, 'listing.paused');
    }

    public function activate(Listing $listing, User $actor): Listing
    {
        return $this->transition($listing, ListingStatus::Published, $actor, 'listing.published', ['via' => 'reactivate']);
    }

    public function markSold(Listing $listing, User $actor): Listing
    {
        return DB::transaction(function () use ($listing, $actor): Listing {
            if (! $listing->status->canTransitionTo(ListingStatus::Sold)) {
                throw $this->invalidTransition();
            }

            $previous = $listing->status;
            $this->applyCountDelta($listing, $previous, ListingStatus::Sold);

            $listing->forceFill([
                'status' => ListingStatus::Sold,
                'sold_at' => now(),
            ])->save();

            $this->auditLog->log('listing.sold', $listing, $actor, [
                'from' => $previous->value,
                'to' => ListingStatus::Sold->value,
            ]);

            return $listing->fresh(['category', 'city', 'district', 'attributeValues.categoryAttribute', 'statistics', 'user']);
        });
    }

    public function renew(Listing $listing, User $actor): Listing
    {
        return $this->transition($listing, ListingStatus::Published, $actor, 'listing.published', ['via' => 'renew']);
    }

    public function archive(Listing $listing, User $actor): Listing
    {
        return $this->transition($listing, ListingStatus::Archived, $actor, 'listing.archived');
    }

    public function restore(Listing $listing, User $actor): Listing
    {
        return $this->transition($listing, ListingStatus::Draft, $actor, 'listing.restored');
    }

    public function block(Listing $listing, User $actor): Listing
    {
        $this->assertModerator($actor);

        return $this->transition($listing, ListingStatus::Blocked, $actor, 'listing.blocked');
    }

    public function softDelete(Listing $listing, User $actor): Listing
    {
        return DB::transaction(function () use ($listing, $actor): Listing {
            $previous = $listing->status;
            $this->applyCountDelta($listing, $previous, ListingStatus::Deleted);

            $listing->forceFill(['status' => ListingStatus::Deleted])->save();
            $listing->delete();

            $this->auditLog->log('listing.deleted', $listing, $actor, [
                'from' => $previous->value,
            ]);

            return $listing->fresh(['category', 'city', 'district', 'attributeValues.categoryAttribute', 'statistics', 'user']);
        });
    }

    private function resolveSubmitTarget(User $actor): ListingStatus
    {
        if ($actor->trusted_seller && $this->settings->getBool('auto_publish_for_trusted_users')) {
            return ListingStatus::Published;
        }

        if ($this->settings->getBool('require_manual_moderation_for_new_users')) {
            return ListingStatus::PendingReview;
        }

        return ListingStatus::PendingReview;
    }

    private function transition(
        Listing $listing,
        ListingStatus $target,
        User $actor,
        string $auditAction,
        array $metadata = [],
    ): Listing {
        return DB::transaction(function () use ($listing, $target, $actor, $auditAction, $metadata): Listing {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $previous = $locked->status;

            if (! $previous->canTransitionTo($target)) {
                throw $this->invalidTransition();
            }

            $this->applyCountDelta($locked, $previous, $target);
            $this->applyTimestamps($locked, $previous, $target);

            $locked->forceFill(['status' => $target])->save();

            $this->auditLog->log($auditAction, $locked, $actor, array_merge([
                'from' => $previous->value,
                'to' => $target->value,
            ], $metadata));

            if ($target === ListingStatus::Published || $previous === ListingStatus::Published) {
                $this->catalogCache->flushCategories();
            }

            return $locked->fresh(['category', 'city', 'district', 'attributeValues.categoryAttribute', 'statistics', 'user']);
        });
    }

    private function applyTimestamps(Listing $listing, ListingStatus $from, ListingStatus $to): void
    {
        if ($to === ListingStatus::Published && ! $from->countsTowardCategoryListingCount()) {
            $days = $this->settings->getInt('default_listing_duration_days', 30);
            $listing->published_at = now();
            $listing->expires_at = now()->addDays($days);
            $listing->rejection_reason = null;
        }

        if ($to === ListingStatus::Sold) {
            $listing->sold_at = now();
        }
    }

    private function applyCountDelta(Listing $listing, ListingStatus $from, ListingStatus $to): void
    {
        $wasCounted = $from->countsTowardCategoryListingCount();
        $willCount = $to->countsTowardCategoryListingCount();

        if ($wasCounted && ! $willCount) {
            $this->listingCounts->decrement($listing->category_id);
        }

        if (! $wasCounted && $willCount) {
            $this->listingCounts->increment($listing->category_id);
        }
    }

    private function assertModerator(User $actor): void
    {
        if (! $actor->hasAnyRole(['moderator', 'admin', 'super_admin'])) {
            throw new ListingException(
                errorCode: 'forbidden',
                message: 'You are not authorized to perform this action.',
                status: Response::HTTP_FORBIDDEN,
            );
        }
    }

    private function invalidTransition(): ListingException
    {
        return new ListingException(
            errorCode: 'listing.invalid_transition',
            message: 'This listing status change is not allowed.',
            status: Response::HTTP_CONFLICT,
            errors: ['listing' => ['listing.invalid_transition']],
        );
    }
}
