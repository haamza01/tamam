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

    public function test_archived_can_restore_to_draft(): void
    {
        $status = ListingStatus::Archived;

        $this->assertTrue($status->canTransitionTo(ListingStatus::Draft));
        $this->assertTrue($status->canTransitionTo(ListingStatus::Published));
        $this->assertTrue($status->canTransitionTo(ListingStatus::Deleted));
    }
}
