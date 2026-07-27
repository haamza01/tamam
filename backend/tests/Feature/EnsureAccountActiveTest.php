<?php

namespace Tests\Feature;

use App\Domain\User\Enums\AccountStatus;
use App\Http\Middleware\EnsureAccountActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureAccountActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_suspended_and_deleted_users_are_rejected(): void
    {
        $middleware = new EnsureAccountActive;

        foreach ([AccountStatus::Blocked, AccountStatus::Suspended, AccountStatus::Deleted] as $status) {
            $user = User::factory()->create(['status' => $status]);

            $request = Request::create('/api/v1/example', 'GET');
            $request->setUserResolver(fn () => $user);

            try {
                $middleware->handle($request, fn () => response()->noContent());
                $this->fail("Expected account status [{$status->value}] to be rejected.");
            } catch (HttpException $exception) {
                $this->assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
            }
        }
    }

    public function test_active_users_pass_through(): void
    {
        $user = User::factory()->create(['status' => AccountStatus::Active]);
        $middleware = new EnsureAccountActive;

        $request = Request::create('/api/v1/example', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response()->noContent());

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }
}
