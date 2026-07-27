<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $this->hasPermission($actor, 'users.view');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $this->hasPermission($actor, 'users.suspend');
    }
}
