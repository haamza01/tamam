<?php

namespace Tests\Unit\Listing;

use App\Domain\Listing\Enums\ListingStatus;
use PHPUnit\Framework\TestCase;

class ListingStatusTest extends TestCase
{
    public function test_draft_can_submit_to_pending_review_or_deleted(): void
    {
        $status = ListingStatus::Draft;

        $this->assertTrue($status->canTransitionTo(ListingStatus::PendingReview));
        $this->assertTrue($status->canTransitionTo(ListingStatus::Published));
        $this->assertTrue($status->canTransitionTo(ListingStatus::Deleted));
        $this->assertFalse($status->canTransitionTo(ListingStatus::Paused));
    }

    public function test_only_published_counts_toward_category_total(): void
    {
        $this->assertTrue(ListingStatus::Published->countsTowardCategoryListingCount());
        $this->assertFalse(ListingStatus::Paused->countsTowardCategoryListingCount());
        $this->assertFalse(ListingStatus::Draft->countsTowardCategoryListingCount());
    }
}
