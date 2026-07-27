<?php

namespace App\Domain\User\Enums;

enum AccountStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';
    case Deleted = 'deleted';

    public function allowsLogin(): bool
    {
        return match ($this) {
            self::Pending, self::Active, self::Suspended => true,
            self::Blocked, self::Deleted => false,
        };
    }

    public function allowsPublishing(): bool
    {
        return $this === self::Active;
    }
}
