<?php

namespace App\Http\Middleware;

use App\Application\Auth\AuthCookieService;
use App\Domain\Auth\Exceptions\AuthException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRefreshCsrf
{
    public function __construct(
        private readonly AuthCookieService $authCookieService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $csrfCookie = (string) $request->cookie($this->authCookieService->csrfCookieName());
        $csrfHeader = (string) $request->header('X-Auth-CSRF');

        $csrfCookie = (string) $request->cookie($this->authCookieService->csrfCookieName());
        $csrfHeader = (string) $request->header('X-Auth-CSRF');

        if ($csrfCookie === '' || $csrfHeader === '' || ! hash_equals($csrfCookie, $csrfHeader)) {
            throw new AuthException(
                errorCode: 'auth.csrf_invalid',
                message: 'Invalid CSRF token.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
