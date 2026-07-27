<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class JwtFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_model_uses_uuid_primary_key(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Str::isUuid($user->id));
    }

    public function test_jwt_token_can_be_generated_for_user(): void
    {
        $user = User::factory()->create();

        $token = JWTAuth::fromUser($user);

        $this->assertNotEmpty($token);
        $this->assertSame($user->id, JWTAuth::setToken($token)->getPayload()->get('sub'));
    }
}
