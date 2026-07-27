<?php

namespace App\Domain\User\Enums;

enum VerificationLevel: string
{
    case None = 'none';
    case Phone = 'phone';
    case Email = 'email';
    case Trusted = 'trusted';
}
