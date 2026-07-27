<?php

namespace Tests\Feature\Auth;

use App\Application\Auth\RefreshTokenService;
use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'phone' => '+97455123456',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        RateLimiter::clear('auth-login');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->phone,
                'password' => 'WrongPassword1!',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'WrongPassword1!',
        ])
            ->assertStatus(429)
            ->assertJsonPath('success', false);
    }

    public function test_refresh_rejects_expired_token(): void
    {
        $user = User::factory()->create([
            'phone' => '+97455111222',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        /** @var RefreshTokenService $refreshTokens */
        $refreshTokens = app(RefreshTokenService::class);
        $issued = $refreshTokens->issue($user);

        RefreshToken::query()
            ->where('token_hash', hash('sha256', $issued['token']))
            ->update(['expires_at' => now()->subMinute()]);

        $this->withAuthRefreshCookies($issued['token'], 'csrf-token')
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('errors.auth.0', 'auth.refresh_expired');
    }

    public function test_otp_verification_rejects_exceeded_attempts(): void
    {
        $user = User::factory()->create([
            'phone' => '+97455333444',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ]);

        $phoneHash = hash('sha256', $user->phone);
        Cache::put('otp:phone_verify:'.$phoneHash, hash('sha256', '123456'), 300);
        Cache::put('otp:attempts:phone_verify:'.$phoneHash, 5, 300);

        $this->withToken($login->json('data.access_token'))
            ->postJson('/api/v1/auth/verify-phone', ['code' => '000000'])
            ->assertStatus(429)
            ->assertJsonPath('errors.auth.0', 'auth.otp_attempts_exceeded');
    }

    public function test_auth_responses_never_expose_password_or_token_hashes(): void
    {
        $user = User::factory()->create([
            'phone' => '+97455444555',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString('$2y$', $content);
        $this->assertStringNotContainsString('token_hash', $content);
        $response->assertJsonMissingPath('data.user.password');
    }

    public function test_login_cookies_use_configured_security_attributes(): void
    {
        config([
            'auth_cookies.secure' => true,
            'auth_cookies.same_site' => 'lax',
            'auth_cookies.path' => '/api/v1/auth',
        ]);

        $user = User::factory()->create([
            'phone' => '+97455666777',
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ]);

        $refreshCookie = $response->getCookie('tamam_refresh_token', decrypt: false);
        $csrfCookie = $response->getCookie('tamam_auth_csrf', decrypt: false);

        $this->assertTrue($refreshCookie->isSecure());
        $this->assertTrue($refreshCookie->isHttpOnly());
        $this->assertSame('lax', strtolower($refreshCookie->getSameSite()));
        $this->assertSame('/api/v1/auth', $refreshCookie->getPath());
        $this->assertFalse($csrfCookie->isHttpOnly());
    }
}
