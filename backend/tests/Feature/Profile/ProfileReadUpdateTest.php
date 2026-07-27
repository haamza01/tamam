<?php

namespace Tests\Feature\Profile;

use App\Domain\User\Enums\AccountStatus;
use App\Domain\User\Enums\UserLanguage;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ProfileReadUpdateTest extends ProfileTestCase
{
    public function test_user_can_read_own_profile(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'bio' => 'Seller bio',
        ]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profile.id', $user->id)
            ->assertJsonPath('data.profile.bio', 'Seller bio')
            ->assertJsonPath('data.profile.avatar_url', '/images/default-avatar.svg')
            ->assertJsonMissingPath('data.profile.password')
            ->assertJsonMissingPath('data.profile.status')
            ->assertJsonMissingPath('data.profile.roles');
    }

    public function test_user_can_update_permitted_fields(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'full_name' => 'Old Name',
        ]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->patchJson('/api/v1/profile', [
                'full_name' => 'New Name',
                'preferred_language' => 'en',
                'bio' => 'Updated bio',
                'username' => 'seller123',
            ])
            ->assertOk()
            ->assertJsonPath('data.profile.full_name', 'New Name')
            ->assertJsonPath('data.profile.preferred_language', 'en')
            ->assertJsonPath('data.profile.username', 'seller123');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => 'New Name',
            'language' => UserLanguage::English->value,
            'bio' => 'Updated bio',
            'username' => 'seller123',
        ]);
    }

    public function test_patch_preserves_omitted_values(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'full_name' => 'Keep Me',
            'bio' => 'Keep bio',
        ]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->patchJson('/api/v1/profile', [
                'preferred_language' => 'en',
            ])
            ->assertOk()
            ->assertJsonPath('data.profile.full_name', 'Keep Me')
            ->assertJsonPath('data.profile.bio', 'Keep bio')
            ->assertJsonPath('data.profile.preferred_language', 'en');
    }

    public function test_validation_failures_use_unified_envelope(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->patchJson('/api/v1/profile', [
                'full_name' => str_repeat('a', 101),
                'preferred_language' => 'fr',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->patchJson('/api/v1/profile', ['email' => 'taken@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'profile.email_taken');
    }

    public function test_protected_fields_cannot_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Password123!'),
            'status' => AccountStatus::Active,
        ]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->patchJson('/api/v1/profile', [
                'phone' => '+97455999999',
                'status' => AccountStatus::Blocked->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'profile.field_protected');
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->patchJson('/api/v1/profile', ['full_name' => 'Nope'])->assertUnauthorized();
    }

    public function test_blocked_suspended_and_deleted_accounts_cannot_access_profile(): void
    {
        foreach ([AccountStatus::Blocked, AccountStatus::Suspended, AccountStatus::Deleted] as $status) {
            $user = User::factory()->create([
                'phone' => '+9745'.random_int(1000000, 9999999),
                'password' => Hash::make('Password123!'),
                'status' => $status,
            ]);
            $user->assignRole('user');

            $token = JWTAuth::fromUser($user);

            $this->withToken($token)
                ->patchJson('/api/v1/profile', ['full_name' => 'Blocked Update'])
                ->assertForbidden();
        }
    }

    public function test_profile_update_creates_audit_event(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->patchJson('/api/v1/profile', ['full_name' => 'Audited Name'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'profile.updated',
            'user_id' => $user->id,
            'entity_id' => $user->id,
        ]);
    }

    public function test_profile_response_does_not_leak_sensitive_fields(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $content = $this->withToken($token)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('$2y$', $content);
        $this->assertStringNotContainsString('refresh_token', $content);
        $this->assertStringNotContainsString('permissions', $content);
    }
}
