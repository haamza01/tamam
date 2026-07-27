<?php

namespace App\Domain\Listing\Enums;

enum PriceType: string
{
    case Fixed = 'fixed';
    case Negotiable = 'negotiable';
    case Free = 'free';
    case ContactForPrice = 'contact_for_price';

    public function requiresPrice(): bool
    {
        return in_array($this, [self::Fixed, self::Negotiable], true);
    }
}
