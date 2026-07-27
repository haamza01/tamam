<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class ProfileTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public_assets');
        config(['media.avatar.disk' => 'public_assets']);
    }

    protected function authenticate(User $user): string
    {
        $user->assignRole('user');

        return (string) $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->phone,
            'password' => 'Password123!',
        ])->json('data.access_token');
    }

    protected function makePngUpload(string $filename = 'avatar.png', int $sizeKb = 1): UploadedFile
    {
        $content = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );

        if ($sizeKb > 1) {
            $content = str_pad($content, $sizeKb * 1024, '0');
        }

        return UploadedFile::fake()->createWithContent($filename, $content);
    }
}
