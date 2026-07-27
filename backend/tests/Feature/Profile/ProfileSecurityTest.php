<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileSecurityTest extends ProfileTestCase
{
    public function test_own_profile_returns_full_phone_number(): void
    {
        $user = User::factory()->create([
            'phone' => '+97455123456',
            'password' => Hash::make('Password123!'),
        ]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.phone', '+97455123456');
    }

    public function test_avatar_upload_rejects_svg_files(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->createWithContent('avatar.svg', $svg),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'profile.avatar_invalid_type');
    }

    public function test_avatar_upload_rejects_gif_files(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => UploadedFile::fake()->createWithContent('avatar.gif', 'GIF89a'.str_repeat('0', 32)),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.avatar.0', 'profile.avatar_invalid_type');
    }

    public function test_avatar_delete_does_not_remove_objects_outside_user_prefix(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $otherUser = User::factory()->create();
        $foreignPath = 'avatars/'.$otherUser->id.'/foreign.png';

        Storage::disk('public_assets')->put($foreignPath, 'foreign-content');
        $user->forceFill(['avatar' => $foreignPath])->save();

        $token = $this->authenticate($user);

        $this->withToken($token)
            ->deleteJson('/api/v1/profile/avatar')
            ->assertOk();

        Storage::disk('public_assets')->assertExists($foreignPath);
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_failed_avatar_save_cleans_up_uploaded_object(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $dispatcher = User::getEventDispatcher();

        try {
            User::saving(function (): void {
                throw new \RuntimeException('Simulated profile save failure.');
            });

            $this->withToken($token)
                ->postJson('/api/v1/profile/avatar', ['avatar' => $this->makePngUpload()])
                ->assertStatus(500);
        } finally {
            User::setEventDispatcher($dispatcher);
        }

        $this->assertSame(0, count(Storage::disk('public_assets')->allFiles('avatars/'.$user->id)));
        $this->assertNull($user->fresh()->avatar);
    }

    public function test_avatar_url_uses_public_base_url_not_internal_paths(): void
    {
        config(['filesystems.disks.public_assets.url' => 'http://localhost:9000/tamam-public']);

        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $token = $this->authenticate($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/profile/avatar', ['avatar' => $this->makePngUpload()])
            ->assertOk();

        $avatarUrl = (string) $response->json('data.profile.avatar_url');

        $this->assertStringStartsWith('http://localhost:9000/tamam-public/avatars/', $avatarUrl);
        $this->assertStringNotContainsString('\\', $avatarUrl);
        $this->assertStringNotContainsString('minio:', $avatarUrl);
    }

    public function test_public_seller_profile_resource_remains_deferred(): void
    {
        $this->assertFalse(class_exists('App\\Http\\Resources\\PublicSellerProfileResource'));
    }
}
