<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    protected function hasPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
