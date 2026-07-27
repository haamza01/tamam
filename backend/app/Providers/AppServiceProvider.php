<?php

namespace App\Providers;

use App\Application\Audit\AuditLogService;
use App\Application\Moderation\ProhibitedWordsChecker;
use App\Application\Platform\PlatformSettingsService;
use App\Domain\Auth\Contracts\OtpProviderInterface;
use App\Infrastructure\Auth\LogOtpProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlatformSettingsService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(ProhibitedWordsChecker::class);

        $this->app->bind(OtpProviderInterface::class, function (): OtpProviderInterface {
            return match (config('otp.driver')) {
                'log' => new LogOtpProvider,
                default => new LogOtpProvider,
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
