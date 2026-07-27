<?php

namespace App\Application\Auth;

use App\Domain\User\Enums\AccountStatus;
use App\Domain\User\Enums\VerificationLevel;
use App\Models\User;

class PhoneVerificationService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly AuthAuditService $authAudit,
    ) {}

    public function send(User $user): void
    {
        $this->otpService->send($user->phone, 'phone_verify');
    }

    public function verify(User $user, string $code): User
    {
        $this->otpService->verify($user->phone, 'phone_verify', $code);

        $user->forceFill([
            'phone_verified_at' => now(),
            'verification_level' => VerificationLevel::Phone,
            'status' => $user->status === AccountStatus::Pending
                ? AccountStatus::Active
                : $user->status,
        ])->save();

        $this->authAudit->phoneVerified($user);

        return $user->fresh();
    }
}
