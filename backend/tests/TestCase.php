<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

abstract class TestCase extends BaseTestCase
{
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
}
