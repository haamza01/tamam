<?php

namespace App\Application\Auth;

use App\Domain\Auth\Exceptions\AuthException;
use App\Domain\User\Enums\AccountStatus;
use App\Domain\User\Enums\AccountType;
use App\Domain\User\Enums\UserLanguage;
use App\Domain\User\Enums\VerificationLevel;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthService
{
    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly AuthAuditService $authAudit,
    ) {}

    /**
     * @param  array{full_name: string, phone: string, email?: string|null, password: string}  $data
     */
    public function register(array $data): User
    {
        $phone = $this->phoneNormalizer->normalize($data['phone']);

        if (User::query()->where('phone', $phone)->exists()) {
            throw new AuthException(
                errorCode: 'auth.phone_taken',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['phone' => ['auth.phone_taken']],
            );
        }

        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;

        if ($email !== null && $email !== '' && User::query()->where('email', $email)->exists()) {
            throw new AuthException(
                errorCode: 'auth.email_taken',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['email' => ['auth.email_taken']],
            );
        }

        $user = User::query()->create([
            'full_name' => $data['full_name'],
            'phone' => $phone,
            'email' => $email ?: null,
            'password' => $data['password'],
            'language' => UserLanguage::Arabic,
            'account_type' => AccountType::Individual,
            'verification_level' => VerificationLevel::None,
            'status' => AccountStatus::Pending,
        ]);

        $user->assignRole('user');
        $this->authAudit->registered($user);

        return $user;
    }

    public function authenticate(string $identifier, string $password): User
    {
        $user = $this->findByIdentifier($identifier);

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->authAudit->loginFailed($identifier);

            throw new AuthException(
                errorCode: 'auth.invalid_credentials',
                message: 'The provided credentials are incorrect.',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        $this->assertLoginAllowed($user);

        $user->forceFill(['last_login_at' => now()])->save();
        $this->authAudit->loginSucceeded($user);

        return $user;
    }

    public function findByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            return User::query()->where('email', strtolower($identifier))->first();
        }

        try {
            $phone = $this->phoneNormalizer->normalize($identifier);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return User::query()->where('phone', $phone)->first();
    }

    public function assertLoginAllowed(User $user): void
    {
        if ($user->status === AccountStatus::Blocked) {
            throw new AuthException(
                errorCode: 'auth.account_blocked',
                message: 'This account has been blocked.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->status === AccountStatus::Suspended) {
            throw new AuthException(
                errorCode: 'auth.account_suspended',
                message: 'This account has been suspended.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->status === AccountStatus::Deleted || $user->trashed()) {
            throw new AuthException(
                errorCode: 'auth.account_deleted',
                message: 'This account is no longer available.',
                status: Response::HTTP_FORBIDDEN,
            );
        }
    }
}
