<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthMeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_auth_me_returns_current_user_without_sensitive_fields(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        $token = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ])->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonMissingPath('data.user.password');
    }
}
