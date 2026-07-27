<?php

namespace Tests\Feature\Auth;

use App\Domain\User\Enums\AccountStatus;
use App\Domain\User\Enums\VerificationLevel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_phone_verification_sets_phone_verified_at(): void
    {
        $user = $this->authenticatedUser();
        $code = '123456';

        Cache::put('otp:phone_verify:'.hash('sha256', $user->phone), hash('sha256', $code), 300);
        Cache::put('otp:attempts:phone_verify:'.hash('sha256', $user->phone), 0, 300);

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/verify-phone', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.user.phone_verified', true);

        $user->refresh();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame(VerificationLevel::Phone, $user->verification_level);
        $this->assertSame(AccountStatus::Active, $user->status);
    }

    public function test_resend_phone_code_respects_cooldown(): void
    {
        $this->authenticatedUser();

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/resend-phone-code')
            ->assertOk();

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/resend-phone-code')
            ->assertStatus(429)
            ->assertJsonPath('errors.auth.0', 'auth.otp_cooldown');
    }

    public function test_otp_is_never_returned_in_api_responses(): void
    {
        $this->authenticatedUser();

        $response = $this->withToken($this->accessToken)
            ->postJson('/api/v1/auth/resend-phone-code');

        $response->assertOk();
        $this->assertStringNotContainsString('123456', $response->getContent());
    }

    private ?string $accessToken = null;

    private function authenticatedUser(): User
    {
        $user = User::factory()->create([
            'phone' => '+97455'.random_int(100000, 999999),
            'password' => Hash::make('Password123!'),
        ]);
        $user->assignRole('user');

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ]);

        $this->accessToken = $login->json('data.access_token');

        return $user;
    }
}
