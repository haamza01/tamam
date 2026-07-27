<?php

namespace Tests\Feature;

use App\Domain\Auth\Contracts\OtpProviderInterface;
use App\Infrastructure\Auth\LogOtpProvider;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OtpProviderTest extends TestCase
{
    public function test_log_otp_provider_is_bound(): void
    {
        $this->assertInstanceOf(LogOtpProvider::class, app(OtpProviderInterface::class));
    }

    public function test_log_otp_provider_writes_to_otp_channel_in_testing(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('otp')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once();

        app(OtpProviderInterface::class)->send('+97450000099', '123456');
    }
}
