<?php

namespace App\Exceptions;

use App\Domain\Auth\Exceptions\AuthException;
use App\Domain\Category\Exceptions\CategoryException;
use App\Domain\Profile\Exceptions\ProfileException;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionHandler
{
    public static function render(Throwable $exception, Request $request): ?Response
    {
        if (! self::shouldRenderJson($request)) {
            return null;
        }

        return match (true) {
            $exception instanceof AuthException => ApiResponse::error(
                message: $exception->getMessage(),
                status: $exception->status(),
                errors: $exception->errors(),
            ),
            $exception instanceof ProfileException => ApiResponse::error(
                message: $exception->getMessage(),
                status: $exception->status(),
                errors: $exception->errors(),
            ),
            $exception instanceof CategoryException => ApiResponse::error(
                message: $exception->getMessage(),
                status: $exception->status(),
                errors: $exception->errors(),
            ),
            $exception instanceof ValidationException => self::renderValidationException($exception),
            $exception instanceof NotFoundHttpException => ApiResponse::error(
                message: 'The requested resource was not found.',
                status: Response::HTTP_NOT_FOUND,
            ),
            $exception instanceof MethodNotAllowedHttpException => ApiResponse::error(
                message: 'The requested HTTP method is not allowed for this endpoint.',
                status: Response::HTTP_METHOD_NOT_ALLOWED,
            ),
            $exception instanceof TooManyRequestsHttpException => ApiResponse::error(
                message: 'Too many requests. Please try again later.',
                status: Response::HTTP_TOO_MANY_REQUESTS,
            ),
            $exception instanceof AuthenticationException => ApiResponse::error(
                message: 'Authentication is required to access this resource.',
                status: Response::HTTP_UNAUTHORIZED,
            ),
            $exception instanceof AuthorizationException => ApiResponse::error(
                message: 'You are not authorized to perform this action.',
                status: Response::HTTP_FORBIDDEN,
            ),
            $exception instanceof HttpException => ApiResponse::error(
                message: $exception->getMessage() ?: 'Request could not be completed.',
                status: $exception->getStatusCode(),
            ),
            default => self::renderServerError($exception),
        };
    }

    private static function shouldRenderJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private static function renderValidationException(ValidationException $exception): Response
    {
        return ApiResponse::fromThrowable(
            exception: $exception,
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            message: 'Validation failed.',
        );
    }

    private static function renderServerError(Throwable $exception): Response
    {
        if (! app()->environment('testing')) {
            Log::error('Unhandled API exception.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        return ApiResponse::error(
            message: 'An unexpected error occurred while processing the request.',
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
