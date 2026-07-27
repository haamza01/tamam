<?php

namespace App\Application\Auth;

use App\Domain\Auth\Contracts\OtpProviderInterface;
use App\Domain\Auth\Exceptions\AuthException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class OtpService
{
    public function __construct(
        private readonly OtpProviderInterface $otpProvider,
    ) {}

    public function send(string $phone, string $purpose): void
    {
        $this->assertValidPurpose($purpose);
        $this->assertResendAllowed($phone, $purpose);

        $code = $this->generateCode();
        $expiry = (int) config('otp.expiry_seconds');

        Cache::put($this->storageKey($phone, $purpose), hash('sha256', $code), $expiry);
        Cache::put($this->attemptsKey($phone, $purpose), 0, $expiry);
        Cache::put($this->cooldownKey($phone, $purpose), true, (int) config('otp.resend_cooldown_seconds'));

        $this->otpProvider->send($phone, $code);
    }

    public function verify(string $phone, string $purpose, string $code): void
    {
        $this->assertValidPurpose($purpose);

        $storageKey = $this->storageKey($phone, $purpose);

        if (! Cache::has($storageKey)) {
            throw new AuthException(
                errorCode: 'auth.otp_expired',
                message: 'The verification code has expired.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $attempts = (int) Cache::get($this->attemptsKey($phone, $purpose), 0);
        $maxAttempts = (int) config('otp.max_attempts');

        if ($attempts >= $maxAttempts) {
            throw new AuthException(
                errorCode: 'auth.otp_attempts_exceeded',
                message: 'Too many invalid verification attempts.',
                status: Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $expectedHash = (string) Cache::get($storageKey);

        if (! hash_equals($expectedHash, hash('sha256', $code))) {
            Cache::put($this->attemptsKey($phone, $purpose), $attempts + 1, (int) config('otp.expiry_seconds'));

            throw new AuthException(
                errorCode: 'auth.otp_invalid',
                message: 'The verification code is invalid.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        Cache::forget($storageKey);
        Cache::forget($this->attemptsKey($phone, $purpose));
        Cache::forget($this->cooldownKey($phone, $purpose));
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function assertResendAllowed(string $phone, string $purpose): void
    {
        if (Cache::has($this->cooldownKey($phone, $purpose))) {
            throw new AuthException(
                errorCode: 'auth.otp_cooldown',
                message: 'Please wait before requesting another verification code.',
                status: Response::HTTP_TOO_MANY_REQUESTS,
            );
        }
    }

    private function assertValidPurpose(string $purpose): void
    {
        if (! in_array($purpose, config('otp.purposes'), true)) {
            throw new AuthException(
                errorCode: 'auth.otp_invalid_purpose',
                message: 'Invalid verification purpose.',
                status: Response::HTTP_BAD_REQUEST,
            );
        }
    }

    private function storageKey(string $phone, string $purpose): string
    {
        return 'otp:'.$purpose.':'.hash('sha256', $phone);
    }

    private function attemptsKey(string $phone, string $purpose): string
    {
        return 'otp:attempts:'.$purpose.':'.hash('sha256', $phone);
    }

    private function cooldownKey(string $phone, string $purpose): string
    {
        return 'otp:cooldown:'.$purpose.':'.hash('sha256', $phone);
    }
}
