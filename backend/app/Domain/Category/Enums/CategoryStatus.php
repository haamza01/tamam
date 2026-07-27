<?php

namespace App\Domain\Category\Enums;

enum CategoryStatus: string
{
    case Active = 'active';
    case Hidden = 'hidden';
    case Archived = 'archived';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Active;
    }
}
