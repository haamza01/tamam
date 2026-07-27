<?php

namespace App\Application\Profile;

use App\Domain\Profile\Exceptions\ProfileException;
use App\Domain\User\Enums\UserLanguage;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class ProfileService
{
    /** @var list<string> */
    private const EDITABLE_FIELDS = [
        'full_name',
        'email',
        'language',
        'username',
        'bio',
    ];

    public function __construct(
        private readonly ProfileAuditService $profileAudit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rawInput
     */
    public function update(User $user, array $data, array $rawInput = []): User
    {
        $payload = $this->normalizePayload($data);
        $this->rejectProtectedFields($rawInput);

        if ($payload === []) {
            return $user->fresh();
        }

        $changedFields = [];

        foreach ($payload as $field => $value) {
            if ($user->{$field} != $value) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields === []) {
            return $user->fresh();
        }

        if (
            array_key_exists('email', $payload)
            && $payload['email'] !== null
            && User::query()
                ->where('email', $payload['email'])
                ->whereKeyNot($user->id)
                ->exists()
        ) {
            throw new ProfileException(
                errorCode: 'profile.email_taken',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['email' => ['profile.email_taken']],
            );
        }

        if (
            array_key_exists('username', $payload)
            && $payload['username'] !== null
            && User::query()
                ->where('username', $payload['username'])
                ->whereKeyNot($user->id)
                ->exists()
        ) {
            throw new ProfileException(
                errorCode: 'profile.username_taken',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['username' => ['profile.username_taken']],
            );
        }

        $user->fill($payload);
        $user->save();

        $this->profileAudit->profileUpdated($user, $changedFields);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        if (array_key_exists('preferred_language', $data)) {
            $data['language'] = $data['preferred_language'];
            unset($data['preferred_language']);
        }

        $payload = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($field === 'email' && is_string($value)) {
                $value = strtolower(trim($value));
                $value = $value === '' ? null : $value;
            }

            if ($field === 'username' && is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            if ($field === 'language' && is_string($value)) {
                $value = UserLanguage::from($value);
            }

            $payload[$field] = $value;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rejectProtectedFields(array $data): void
    {
        $protected = [
            'phone',
            'password',
            'status',
            'account_type',
            'verification_level',
            'phone_verified_at',
            'email_verified_at',
            'trusted_seller',
            'roles',
            'permissions',
            'avatar',
            'country_id',
            'city_id',
            'last_login_at',
        ];

        foreach ($protected as $field) {
            if (array_key_exists($field, $data)) {
                throw new ProfileException(
                    errorCode: 'profile.field_protected',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: [$field => ['profile.field_protected']],
                );
            }
        }
    }
}
