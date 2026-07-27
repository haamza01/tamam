<?php

namespace Tests\Feature\Profile;

use App\Application\Profile\AvatarStorageService;
use App\Domain\Profile\Exceptions\ProfileException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileAvatarTest extends ProfileTestCase
{
    public function test_user_can_upload_avatar(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $this->makePngUpload(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $avatarUrl = $response->json('data.profile.avatar_url');
        $this->assertNotSame('/images/default-avatar.svg', $avatarUrl);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        Storage::disk('public_assets')->assertExists($user->avatar);
    }

    public function test_avatar_upload_rejects_invalid_mime_type(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->createWithContent('avatar.jpg', 'plain text content'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'profile.avatar_invalid_type');
    }

    public function test_avatar_upload_rejects_oversized_file(): void
    {
        config(['media.avatar.max_kb' => 1]);

        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $this->makePngUpload('large.png', sizeKb: 2),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'profile.avatar_too_large');
    }

    public function test_avatar_upload_rejects_executable_disguised_as_image(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo "x"; ?>'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'profile.avatar_invalid_type');
    }

    public function test_avatar_replacement_removes_previous_object(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', ['avatar' => $this->makePngUpload('first.png')])
            ->assertOk();

        $firstPath = $user->fresh()->avatar;

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', ['avatar' => $this->makePngUpload('second.png')])
            ->assertOk();

        $secondPath = $user->fresh()->avatar;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public_assets')->assertMissing($firstPath);
        Storage::disk('public_assets')->assertExists($secondPath);
    }

    public function test_user_can_delete_avatar(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', ['avatar' => $this->makePngUpload()])
            ->assertOk();

        $storedPath = $user->fresh()->avatar;

        $this->withToken($token)
            ->deleteJson('/api/v1/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.profile.avatar_url', '/images/default-avatar.svg');

        Storage::disk('public_assets')->assertMissing($storedPath);
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_avatar_storage_failure_returns_safe_error(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->mock(AvatarStorageService::class, function ($mock): void {
            $mock->shouldReceive('replace')->andThrow(new ProfileException(
                errorCode: 'profile.avatar_storage_failed',
                message: 'Unable to store avatar at this time.',
                status: 500,
            ));
        });

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', ['avatar' => $this->makePngUpload()])
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.profile.0', 'profile.avatar_storage_failed');
    }

    public function test_avatar_upload_creates_audit_event(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', ['avatar' => $this->makePngUpload()])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'profile.avatar.uploaded',
            'user_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_avatar_upload_is_rejected(): void
    {
        $this->postJson('/api/v1/profile/avatar', [
            'avatar' => $this->makePngUpload(),
        ])->assertUnauthorized();
    }
}
