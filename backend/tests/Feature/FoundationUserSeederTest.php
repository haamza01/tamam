<?php

namespace Tests\Feature;

use App\Domain\Auth\Contracts\OtpProviderInterface;
use App\Infrastructure\Auth\LogOtpProvider;
use Database\Seeders\FoundationUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FoundationUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_users_are_seeded_only_in_testing_environment(): void
    {
        $this->seed(FoundationUserSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'super@tamam.local']);
        $this->assertDatabaseHas('users', ['email' => 'admin@tamam.local']);
        $this->assertDatabaseHas('users', ['email' => 'mod@tamam.local']);
    }

    public function test_log_otp_provider_rejects_non_local_environments(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Log OTP provider is not available outside local and testing environments.');

            (new LogOtpProvider)->send('+97450000099', '123456');
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
            $this->app->forgetInstance(OtpProviderInterface::class);
        }
    }

    public function test_otp_provider_binding_rejects_log_driver_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Log OTP provider cannot be configured outside local and testing environments.');

            app(OtpProviderInterface::class);
        } finally {
            $this->app->detectEnvironment(fn (): string => 'testing');
            $this->app->forgetInstance(OtpProviderInterface::class);
        }
    }
}
