<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiErrorEnvelopeTest extends TestCase
{
    public function test_not_found_returns_unified_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/unknown-endpoint');

        $response
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'The requested resource was not found.',
                'errors' => [],
                'data' => null,
            ]);
    }

    public function test_method_not_allowed_returns_unified_error_envelope(): void
    {
        $response = $this->postJson('/api/v1/health');

        $response
            ->assertStatus(405)
            ->assertJson([
                'success' => false,
                'message' => 'The requested HTTP method is not allowed for this endpoint.',
                'errors' => [],
                'data' => null,
            ]);
    }

    public function test_validation_error_returns_unified_error_envelope(): void
    {
        Route::post('/api/v1/_test/validation', function () {
            request()->validate([
                'name' => ['required', 'string'],
            ]);
        });

        $response = $this->postJson('/api/v1/_test/validation', []);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
                'data' => null,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'name',
                ],
                'data',
            ]);
    }

    public function test_rate_limit_returns_unified_error_envelope(): void
    {
        Route::middleware('throttle:1,1')->get('/api/v1/_test/rate-limit', fn () => response()->json(['ok' => true]));

        $this->getJson('/api/v1/_test/rate-limit')->assertOk();
        $response = $this->getJson('/api/v1/_test/rate-limit');

        $response
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'errors' => [],
                'data' => null,
            ]);
    }

    public function test_server_error_returns_unified_error_envelope_without_internal_details(): void
    {
        Route::get('/api/v1/_test/server-error', function () {
            throw new \RuntimeException('Sensitive internal failure details.');
        });

        $response = $this->getJson('/api/v1/_test/server-error');

        $response
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An unexpected error occurred while processing the request.',
                'errors' => [],
                'data' => null,
            ]);

        $this->assertStringNotContainsString(
            'Sensitive internal failure details.',
            (string) $response->getContent(),
        );
    }
}
