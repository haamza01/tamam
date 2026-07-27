<?php

namespace App\Application\Auth;

use App\Application\Audit\AuditLogService;
use App\Models\User;

class AuthAuditService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function loginSucceeded(User $user): void
    {
        $this->auditLogService->log('auth.login.succeeded', $user, $user);
    }

    public function loginFailed(?string $identifier): void
    {
        $this->auditLogService->log('auth.login.failed', metadata: [
            'identifier_hash' => $identifier ? hash('sha256', $identifier) : null,
        ]);
    }

    public function logout(User $user): void
    {
        $this->auditLogService->log('auth.logout', $user, $user);
    }

    public function logoutAll(User $user): void
    {
        $this->auditLogService->log('auth.logout.all', $user, $user);
    }

    public function registered(User $user): void
    {
        $this->auditLogService->log('auth.registered', $user, $user);
    }

    public function phoneVerified(User $user): void
    {
        $this->auditLogService->log('auth.phone.verified', $user, $user);
    }

    public function passwordReset(User $user): void
    {
        $this->auditLogService->log('auth.password.reset', $user, $user);
    }

    public function refreshTokenReuseDetected(User $user): void
    {
        $this->auditLogService->log('auth.refresh.reuse_detected', $user, $user);
    }

    public function tokensRevoked(User $user, string $reason): void
    {
        $this->auditLogService->log('auth.tokens.revoked', $user, $user, metadata: [
            'reason' => $reason,
        ]);
    }
}
