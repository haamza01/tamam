<?php

namespace App\Http\Middleware;

use App\Domain\User\Enums\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if (in_array($user->status, [
            AccountStatus::Blocked,
            AccountStatus::Suspended,
            AccountStatus::Deleted,
        ], true)) {
            abort(Response::HTTP_FORBIDDEN, 'This account is not allowed to access the platform.');
        }

        return $next($request);
    }
}
