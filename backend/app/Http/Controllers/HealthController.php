<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'status' => 'ok',
                'service' => 'tamam-api',
                'api_version' => 'v1',
            ],
            message: 'Service is healthy.',
        );
    }
}
