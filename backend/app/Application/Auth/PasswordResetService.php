<?php

namespace App\Application\Auth;

use App\Domain\Auth\Exceptions\AuthException;
use App\Domain\User\Enums\AccountStatus;
use Symfony\Component\HttpFoundation\Response;

class PasswordResetService
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly OtpService $otpService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly AuthAuditService $authAudit,
    ) {}

    public function requestReset(string $identifier): void
    {
        $user = $this->authService->findByIdentifier($identifier);

        if ($user === null) {
            return;
        }

        if (in_array($user->status, [AccountStatus::Blocked, AccountStatus::Deleted], true) || $user->trashed()) {
            return;
        }

        $this->otpService->send($user->phone, 'password_reset');
    }

    public function resetPassword(string $identifier, string $code, string $password): void
    {
        $user = $this->authService->findByIdentifier($identifier);

        if ($user === null) {
            throw new AuthException(
                errorCode: 'auth.invalid_credentials',
                message: 'The provided credentials are incorrect.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->authService->assertLoginAllowed($user);
        $this->otpService->verify($user->phone, 'password_reset', $code);

        $user->forceFill(['password' => $password])->save();
        $this->refreshTokenService->revokeAllForUser($user);
        $this->authAudit->passwordReset($user);
        $this->authAudit->tokensRevoked($user, 'password_reset');
    }
}
