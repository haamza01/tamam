<?php

namespace Tests\Feature\Auth;

use App\Application\Auth\RefreshTokenService;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_refresh_rotates_token_and_sets_secure_cookies(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $this->createUser()->phone,
            'password' => 'Password123!',
        ])->assertOk();

        $refreshCookie = $this->authCookieFromResponse($login, 'tamam_refresh_token');
        $csrfCookie = $this->authCookieFromResponse($login, 'tamam_auth_csrf');

        $response = $this->withAuthRefreshCookies(
            $refreshCookie->getValue(),
            $csrfCookie->getValue(),
        )->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token']]);

        $newRefresh = $this->authCookieFromResponse($response, 'tamam_refresh_token');
        $this->assertNotSame($refreshCookie->getValue(), $newRefresh->getValue());
    }

    public function test_refresh_rejects_missing_csrf_header(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $this->createUser()->phone,
            'password' => 'Password123!',
        ]);

        $refreshCookie = $this->authCookieFromResponse($login, 'tamam_refresh_token');

        $this->withAuthRefreshCookies($refreshCookie->getValue())
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden()
            ->assertJsonPath('errors.auth.0', 'auth.csrf_invalid');
    }

    public function test_reused_refresh_token_revokes_all_sessions(): void
    {
        $user = $this->createUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ])->assertOk();

        $oldRefresh = $this->authCookieFromResponse($login, 'tamam_refresh_token')->getValue();
        $csrf = $this->authCookieFromResponse($login, 'tamam_auth_csrf')->getValue();

        $this->withAuthRefreshCookies($oldRefresh, $csrf)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk();

        $this->withAuthRefreshCookies($oldRefresh, $csrf)
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('errors.auth.0', 'auth.refresh_reused');

        $this->assertSame(
            0,
            $user->refreshTokens()->whereNull('revoked_at')->count(),
        );
    }

    public function test_logout_revokes_current_refresh_token(): void
    {
        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $this->createUser()->phone,
            'password' => 'Password123!',
        ]);

        $accessToken = $login->json('data.access_token');
        $refreshCookie = $this->authCookieFromResponse($login, 'tamam_refresh_token');

        $this->withToken($accessToken)
            ->withAuthRefreshCookies($refreshCookie->getValue())
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseHas('refresh_tokens', [
            'token_hash' => hash('sha256', $refreshCookie->getValue()),
        ]);

        $this->assertNotNull(
            RefreshToken::query()->where('token_hash', hash('sha256', $refreshCookie->getValue()))->value('revoked_at'),
        );
    }

    public function test_logout_all_revokes_all_refresh_tokens(): void
    {
        $user = $this->createUser();
        /** @var RefreshTokenService $refreshTokens */
        $refreshTokens = app(RefreshTokenService::class);
        $refreshTokens->issue($user);
        $refreshTokens->issue($user);

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ]);

        $this->withToken($login->json('data.access_token'))
            ->postJson('/api/v1/auth/logout-all')
            ->assertOk();

        $this->assertSame(0, $user->refreshTokens()->whereNull('revoked_at')->count());
    }

    private function createUser(): User
    {
        $user = User::factory()->create([
            'phone' => '+97455'.random_int(100000, 999999),
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        return $user;
    }
}
