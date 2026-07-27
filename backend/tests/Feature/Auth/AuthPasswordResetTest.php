<?php

namespace Tests\Feature\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_forgot_password_does_not_reveal_account_existence(): void
    {
        $existing = User::factory()->create(['phone' => '+97455111111']);
        $existing->assignRole('user');

        $missing = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => '+97455999999',
        ]);

        $existingResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => $existing->phone,
        ]);

        $missing->assertOk()->assertJsonPath('message', $existingResponse->json('message'));
    }

    public function test_password_reset_revokes_refresh_tokens(): void
    {
        $user = User::factory()->create([
            'phone' => '+97455222222',
            'password' => Hash::make('OldPassword1!'),
        ]);
        $user->assignRole('user');

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'existing-token'),
            'expires_at' => now()->addDay(),
        ]);

        $code = '654321';
        Cache::put('otp:password_reset:'.hash('sha256', $user->phone), hash('sha256', $code), 300);
        Cache::put('otp:attempts:password_reset:'.hash('sha256', $user->phone), 0, 300);

        $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => $user->phone,
            'code' => $code,
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ])->assertOk();

        $this->assertSame(0, $user->refreshTokens()->whereNull('revoked_at')->count());
        $this->assertTrue(Hash::check('NewPassword1!', $user->fresh()->password));
    }
}
