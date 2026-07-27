<?php

namespace App\Application\Profile;

use App\Application\Audit\AuditLogService;
use App\Models\User;

class ProfileAuditService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  list<string>  $changedFields
     */
    public function profileUpdated(User $user, array $changedFields): void
    {
        $this->auditLogService->log(
            action: 'profile.updated',
            entity: $user,
            actor: $user,
            metadata: ['changed_fields' => $changedFields],
        );
    }

    public function avatarUploaded(User $user): void
    {
        $this->auditLogService->log(
            action: 'profile.avatar.uploaded',
            entity: $user,
            actor: $user,
        );
    }

    public function avatarDeleted(User $user): void
    {
        $this->auditLogService->log(
            action: 'profile.avatar.deleted',
            entity: $user,
            actor: $user,
        );
    }
}
