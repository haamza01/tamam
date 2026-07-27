<?php

namespace Tests\Feature;

use App\Application\Audit\AuditLogService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_metadata_values_are_redacted(): void
    {
        $actor = User::factory()->create();

        /** @var AuditLogService $auditLog */
        $auditLog = app(AuditLogService::class);

        $entry = $auditLog->log(
            action: 'user.updated',
            entity: $actor,
            actor: $actor,
            metadata: [
                'password' => 'plain-text-password',
                'nested' => [
                    'refresh_token' => 'secret-token',
                    'safe' => 'visible',
                ],
            ],
        );

        $this->assertSame('[redacted]', $entry->metadata['password']);
        $this->assertSame('[redacted]', $entry->metadata['nested']['refresh_token']);
        $this->assertSame('visible', $entry->metadata['nested']['safe']);
    }
}
