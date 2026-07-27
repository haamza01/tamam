<?php

namespace Tests\Feature;

use App\Application\Audit\AuditLogService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_records_security_action(): void
    {
        $actor = User::factory()->create();

        /** @var AuditLogService $auditLog */
        $auditLog = app(AuditLogService::class);

        $entry = $auditLog->log(
            action: 'role.assigned',
            entity: $actor,
            actor: $actor,
            metadata: ['role' => 'admin'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $entry->id,
            'action' => 'role.assigned',
            'user_id' => $actor->id,
        ]);
    }
}
