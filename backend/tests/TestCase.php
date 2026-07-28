<?php

namespace Tests;

use App\Application\Platform\PlatformSettingsService;
use App\Domain\Auth\Contracts\OtpProviderInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Cookie;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, mixed> */
    private static array $defaultConfigValues = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->captureDefaultConfigValues();
        $this->resetSharedTestState();
    }

    protected function tearDown(): void
    {
        $this->resetSharedTestState();
        $this->restoreDefaultConfigValues();

        parent::tearDown();
    }

    protected function withAuthRefreshCookies(string $refreshToken, ?string $csrfToken = null): static
    {
        $this->withCredentials()
            ->withCookie(
                (string) config('auth_cookies.refresh_cookie'),
                $refreshToken,
            );

        if ($csrfToken !== null) {
            $this->withCookie(
                (string) config('auth_cookies.csrf_cookie'),
                $csrfToken,
            )->withHeader('X-Auth-CSRF', $csrfToken);
        }

        return $this;
    }

    protected function authCookieFromResponse(TestResponse $response, string $name): Cookie
    {
        return $response->getCookie($name);
    }

    protected function resetSharedTestState(): void
    {
        Carbon::setTestNow();

        $this->app->detectEnvironment(static fn (): string => 'testing');

        auth()->forgetGuards();

        if ($this->app->bound('tymon.jwt')) {
            $this->app->make('tymon.jwt')->unsetToken();
        }

        JWTAuth::unsetToken();

        $this->app->forgetInstance(OtpProviderInterface::class);

        Cache::flush();

        app(PlatformSettingsService::class)->flushCache();

        foreach ([
            'auth-register',
            'auth-login',
            'auth-refresh',
            'auth-password',
            'auth-otp',
            'auth-otp-resend',
            'profile-update',
            'profile-avatar',
            'listing-write',
            'listing-image',
            'search',
            'search-suggestions',
            'search-popular',
            'favorite',
        ] as $limiter) {
            RateLimiter::clear($limiter);
        }
    }

    private function captureDefaultConfigValues(): void
    {
        if (self::$defaultConfigValues !== []) {
            return;
        }

        self::$defaultConfigValues = [
            'media.avatar.max_kb' => config('media.avatar.max_kb'),
            'media.avatar.disk' => config('media.avatar.disk'),
            'filesystems.disks.public_assets.driver' => config('filesystems.disks.public_assets.driver'),
            'filesystems.disks.public_assets.url' => config('filesystems.disks.public_assets.url'),
            'otp.driver' => config('otp.driver'),
        ];
    }

    private function restoreDefaultConfigValues(): void
    {
        config(self::$defaultConfigValues);
    }
}
