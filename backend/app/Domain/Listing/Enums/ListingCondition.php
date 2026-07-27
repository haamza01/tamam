<?php

namespace App\Domain\Listing\Enums;

enum ListingCondition: string
{
    case New = 'new';
    case Used = 'used';
    case Refurbished = 'refurbished';
}
