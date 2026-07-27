<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Operation completed successfully.', int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'data' => null,
        ], $status);
    }

    public static function fromThrowable(Throwable $exception, int $status, string $message, array $errors = []): JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return self::error(
                message: $message,
                status: $status,
                errors: $exception->errors(),
            );
        }

        return self::error(
            message: $message,
            status: $status,
            errors: $errors,
        );
    }
}
