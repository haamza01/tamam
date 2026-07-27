<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Exceptions\AuthException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isPhoneVerified()) {
            throw new AuthException(
                errorCode: 'auth.phone_not_verified',
                message: 'Phone verification is required before managing listings.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
