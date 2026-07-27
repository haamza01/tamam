<?php

namespace App\Providers;

use App\Application\Audit\AuditLogService;
use App\Application\Auth\AuthAuditService;
use App\Application\Auth\AuthCookieService;
use App\Application\Auth\AuthService;
use App\Application\Auth\OtpService;
use App\Application\Auth\PasswordResetService;
use App\Application\Auth\PhoneNormalizer;
use App\Application\Auth\PhoneVerificationService;
use App\Application\Auth\RefreshTokenService;
use App\Application\Moderation\ProhibitedWordsChecker;
use App\Application\Platform\PlatformSettingsService;
use App\Domain\Auth\Contracts\OtpProviderInterface;
use App\Infrastructure\Auth\LogOtpProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlatformSettingsService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(ProhibitedWordsChecker::class);
        $this->app->singleton(PhoneNormalizer::class);
        $this->app->singleton(OtpService::class);
        $this->app->singleton(RefreshTokenService::class);
        $this->app->singleton(AuthService::class);
        $this->app->singleton(AuthAuditService::class);
        $this->app->singleton(AuthCookieService::class);
        $this->app->singleton(PhoneVerificationService::class);
        $this->app->singleton(PasswordResetService::class);

        $this->app->bind(OtpProviderInterface::class, function (): OtpProviderInterface {
            $driver = (string) config('otp.driver');

            if ($driver !== 'log') {
                throw new RuntimeException("Unsupported OTP driver [{$driver}] configured.");
            }

            if (! app()->environment('local', 'testing')) {
                throw new RuntimeException('Log OTP provider cannot be configured outside local and testing environments.');
            }

            return new LogOtpProvider;
        });
    }

    public function boot(): void
    {
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('auth-refresh', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('auth-password', fn (Request $request) => Limit::perHour(3)->by($request->ip().':'.$request->input('identifier', 'unknown')));
        RateLimiter::for('auth-otp', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('auth-otp-resend', fn (Request $request) => Limit::perHour(3)->by($request->user()?->id ?: $request->ip()));
    }
}
