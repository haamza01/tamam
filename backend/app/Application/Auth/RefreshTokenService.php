<?php

namespace App\Application\Auth;

use App\Domain\Auth\Exceptions\AuthException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenService
{
    /**
     * @return array{token: string, model: RefreshToken}
     */
    public function issue(User $user): array
    {
        $plainToken = Str::random(64);
        $expiresAt = now()->addDays((int) config('auth_cookies.refresh_ttl_days'));

        $model = RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($plainToken),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'model' => $model,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, user: User}
     */
    public function rotate(string $plainToken): array
    {
        $existing = $this->findByPlainToken($plainToken);

        if ($existing === null) {
            throw new AuthException(
                errorCode: 'auth.refresh_invalid',
                message: 'Refresh token is invalid.',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($existing->isRevoked()) {
            $this->revokeAllForUser($existing->user);
            $this->auditReuse($existing);

            throw new AuthException(
                errorCode: 'auth.refresh_reused',
                message: 'Refresh token has been revoked.',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        if ($existing->isExpired()) {
            $this->revokeToken($existing);

            throw new AuthException(
                errorCode: 'auth.refresh_expired',
                message: 'Refresh token has expired.',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        $user = $existing->user;
        $this->revokeToken($existing);

        $issued = $this->issue($user);

        return [
            'access_token' => $this->createAccessToken($user),
            'refresh_token' => $issued['token'],
            'user' => $user,
        ];
    }

    public function revokeByPlainToken(?string $plainToken): void
    {
        if ($plainToken === null || $plainToken === '') {
            return;
        }

        $token = $this->findByPlainToken($plainToken);

        if ($token !== null) {
            $this->revokeToken($token);
        }
    }

    public function revokeAllForUser(User $user): void
    {
        RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function createAccessToken(User $user): string
    {
        return auth('api')->login($user);
    }

    private function findByPlainToken(string $plainToken): ?RefreshToken
    {
        return RefreshToken::query()
            ->where('token_hash', $this->hashToken($plainToken))
            ->with('user')
            ->first();
    }

    private function revokeToken(RefreshToken $token): void
    {
        if ($token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
        }
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function auditReuse(RefreshToken $token): void
    {
        app(AuthAuditService::class)->refreshTokenReuseDetected($token->user);
    }
}
