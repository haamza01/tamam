<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class AuthCookieService
{
    public function createCsrfToken(): string
    {
        return Str::random(40);
    }

    public function makeRefreshCookie(string $refreshToken): SymfonyCookie
    {
        return $this->makeCookie(
            name: (string) config('auth_cookies.refresh_cookie'),
            value: $refreshToken,
            httpOnly: true,
        );
    }

    public function makeCsrfCookie(string $csrfToken): SymfonyCookie
    {
        return $this->makeCookie(
            name: (string) config('auth_cookies.csrf_cookie'),
            value: $csrfToken,
            httpOnly: false,
        );
    }

    public function forgetAuthCookies(): array
    {
        $path = (string) config('auth_cookies.path');

        return [
            Cookie::forget((string) config('auth_cookies.refresh_cookie'), $path),
            Cookie::forget((string) config('auth_cookies.csrf_cookie'), $path),
        ];
    }

    public function refreshCookieName(): string
    {
        return (string) config('auth_cookies.refresh_cookie');
    }

    public function csrfCookieName(): string
    {
        return (string) config('auth_cookies.csrf_cookie');
    }

    private function makeCookie(string $name, string $value, bool $httpOnly): SymfonyCookie
    {
        $minutes = (int) config('auth_cookies.refresh_ttl_days') * 24 * 60;

        return Cookie::make(
            name: $name,
            value: $value,
            minutes: $minutes,
            path: (string) config('auth_cookies.path'),
            secure: (bool) config('auth_cookies.secure'),
            httpOnly: $httpOnly,
            sameSite: (string) config('auth_cookies.same_site'),
        );
    }
}
