<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_register_with_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Hamza Seller',
            'phone' => '55123456',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.full_name', 'Hamza Seller')
            ->assertJsonMissingPath('data.access_token');

        $this->assertDatabaseHas('users', [
            'phone' => '+97455123456',
            'full_name' => 'Hamza Seller',
        ]);

        $user = User::query()->where('phone', '+97455123456')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasPermission('listings.moderate'));
    }

    public function test_registration_rejects_duplicate_phone(): void
    {
        User::factory()->create(['phone' => '+97455123456']);

        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Another User',
            'phone' => '+97455123456',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'auth.phone_taken');
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'seller@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Another User',
            'phone' => '55999999',
            'email' => 'seller@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'auth.email_taken');
    }

    public function test_registration_validation_failures_use_unified_envelope(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }
}
