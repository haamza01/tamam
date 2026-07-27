<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ValidateRefreshCsrf;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            env('AUTH_REFRESH_COOKIE', 'tamam_refresh_token'),
            env('AUTH_CSRF_COOKIE', 'tamam_auth_csrf'),
        ]);

        $middleware->appendToGroup('api', [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'permission' => EnsurePermission::class,
            'account.active' => EnsureAccountActive::class,
            'refresh.csrf' => ValidateRefreshCsrf::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, $request) {
            return ApiExceptionHandler::render($exception, $request);
        });
    })->create();
