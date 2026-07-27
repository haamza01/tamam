<?php

namespace App\Infrastructure\Auth;

use App\Domain\Auth\Contracts\OtpProviderInterface;
use Illuminate\Support\Facades\Log;

class LogOtpProvider implements OtpProviderInterface
{
    public function send(string $phone, string $code): void
    {
        if (! app()->environment('local', 'testing')) {
            Log::channel('otp')->warning('OTP delivery skipped outside local/testing environments.', [
                'phone_hash' => hash('sha256', $phone),
            ]);

            return;
        }

        Log::channel('otp')->info('OTP generated for development use only.', [
            'phone_hash' => hash('sha256', $phone),
            'code' => $code,
        ]);
    }
}
