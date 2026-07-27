<?php

namespace App\Providers;

use App\Application\Audit\AuditLogService;
use App\Application\Moderation\ProhibitedWordsChecker;
use App\Application\Platform\PlatformSettingsService;
use App\Domain\Auth\Contracts\OtpProviderInterface;
use App\Infrastructure\Auth\LogOtpProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlatformSettingsService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(ProhibitedWordsChecker::class);

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
        //
    }
}
