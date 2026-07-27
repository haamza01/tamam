<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_unified_success_envelope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Service is healthy.',
                'data' => [
                    'status' => 'ok',
                    'service' => 'tamam-api',
                    'api_version' => 'v1',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'service',
                    'api_version',
                ],
            ]);

        $payload = $response->json();

        $this->assertArrayNotHasKey('APP_KEY', $payload);
        $this->assertArrayNotHasKey('DB_PASSWORD', $payload);
        $this->assertStringNotContainsString('vendor', json_encode($payload));
    }
}
