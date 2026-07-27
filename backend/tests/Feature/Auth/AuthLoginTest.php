<?php

namespace Tests\Feature\Auth;

use App\Domain\User\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_login_with_phone_and_receive_tokens(): void
    {
        $user = User::factory()->create([
            'phone' => '+97455123456',
            'password' => Hash::make('Password123!'),
            'status' => AccountStatus::Active,
        ]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '55123456',
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user']])
            ->assertJsonMissingPath('data.user.password');

        $this->assertNotEmpty($response->json('data.access_token'));
        $refreshCookie = $this->authCookieFromResponse($response, 'tamam_refresh_token');
        $csrfCookie = $this->authCookieFromResponse($response, 'tamam_auth_csrf');
        $this->assertNotNull($refreshCookie);
        $this->assertNotNull($csrfCookie);
        $this->assertTrue($refreshCookie->isHttpOnly());
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'phone' => '+97455123456',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '+97455123456',
            'password' => 'WrongPassword1!',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('errors.auth.0', 'auth.invalid_credentials');
    }

    public function test_login_rejects_blocked_suspended_and_deleted_accounts(): void
    {
        foreach ([AccountStatus::Blocked, AccountStatus::Suspended, AccountStatus::Deleted] as $status) {
            $user = User::factory()->create([
                'phone' => '+9745'.random_int(1000000, 9999999),
                'password' => Hash::make('Password123!'),
                'status' => $status,
            ]);

            $response = $this->postJson('/api/v1/auth/login', [
                'identifier' => $user->phone,
                'password' => 'Password123!',
            ]);

            $response->assertForbidden();
        }
    }
}
