<?php

namespace App\Application\Auth;

use InvalidArgumentException;

class PhoneNormalizer
{
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        if (str_starts_with($digits, '00974')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '974')) {
            $national = substr($digits, 3);
        } elseif (strlen($digits) === 8) {
            $national = $digits;
        } else {
            throw new InvalidArgumentException('Invalid Qatar phone number.');
        }

        if (! preg_match('/^[3567]\d{7}$/', $national)) {
            throw new InvalidArgumentException('Invalid Qatar phone number.');
        }

        return '+974'.$national;
    }
}
