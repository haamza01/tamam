<?php

namespace App\Infrastructure\Auth;

use App\Domain\Auth\Contracts\OtpProviderInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LogOtpProvider implements OtpProviderInterface
{
    public function send(string $phone, string $code): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Log OTP provider is not available outside local and testing environments.');
        }

        Log::channel('otp')->info('OTP generated for development use only.', [
            'phone_hash' => hash('sha256', $phone),
            'code' => $code,
        ]);
    }
}
