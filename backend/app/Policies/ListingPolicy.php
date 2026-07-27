<?php

namespace App\Policies;

use App\Domain\Listing\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;

class ListingPolicy extends BasePolicy
{
    public function view(?User $user, Listing $listing): bool
    {
        if ($listing->isPubliclyVisibleNow()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return $listing->isOwnedBy($user) || $user->hasAnyRole(['moderator', 'admin', 'super_admin']);
    }

    public function create(User $user): bool
    {
        return $user->isPhoneVerified() && $user->isActiveAccount();
    }

    public function update(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user)
            && $user->isActiveAccount()
            && $listing->isEditableByOwner();
    }

    public function delete(User $user, Listing $listing): bool
    {
        if (! $listing->isOwnedBy($user) || ! $user->isActiveAccount()) {
            return false;
        }

        if ($listing->isSoftDeleted()) {
            return true;
        }

        return $listing->status->canTransitionTo(ListingStatus::Deleted);
    }

    public function transition(User $user, Listing $listing): bool
    {
        return $listing->isOwnedBy($user) && $user->isActiveAccount();
    }

    public function moderate(User $user): bool
    {
        return $user->hasAnyRole(['moderator', 'admin', 'super_admin']);
    }
}
