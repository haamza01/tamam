<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_roles_and_permissions_are_seeded(): void
    {
        $this->assertDatabaseHas('roles', ['slug' => 'user']);
        $this->assertDatabaseHas('roles', ['slug' => 'moderator']);
        $this->assertDatabaseHas('roles', ['slug' => 'admin']);
        $this->assertDatabaseHas('roles', ['slug' => 'super_admin']);
        $this->assertDatabaseHas('permissions', ['slug' => 'listings.moderate']);
    }

    public function test_super_admin_has_all_permissions_via_gate_bypass(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->assertTrue($user->hasPermission('roles.manage'));
        $this->assertTrue($user->hasPermission('nonexistent.permission'));
    }

    public function test_moderator_has_expected_permissions_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('moderator');

        $this->assertTrue($user->hasPermission('listings.moderate'));
        $this->assertFalse($user->hasPermission('settings.manage'));
    }

    public function test_regular_user_has_no_admin_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertFalse($user->hasPermission('listings.moderate'));
    }

    public function test_user_policy_allows_self_view_and_admin_list_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($user->can('view', $user));
        $this->assertFalse($user->can('viewAny', User::class));
        $this->assertTrue($admin->can('viewAny', User::class));
    }
}
