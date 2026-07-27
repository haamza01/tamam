<?php

namespace App\Domain\Listing\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Sold = 'sold';
    case Expired = 'expired';
    case Archived = 'archived';
    case Blocked = 'blocked';
    case Deleted = 'deleted';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    public function countsTowardCategoryListingCount(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Published, self::Deleted],
            self::PendingReview => [self::Published, self::Rejected, self::Blocked, self::Deleted],
            self::Rejected => [self::PendingReview, self::Deleted],
            self::Published => [self::Paused, self::Sold, self::Expired, self::Archived, self::PendingReview, self::Blocked, self::Deleted],
            self::Paused => [self::Published, self::Archived, self::Deleted],
            self::Sold => [self::Archived, self::Deleted],
            self::Expired => [self::Published, self::Archived, self::Deleted],
            self::Archived => [self::Published, self::Deleted],
            self::Blocked => [self::Deleted],
            self::Deleted => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
