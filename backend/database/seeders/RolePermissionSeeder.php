<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View Users', 'slug' => 'users.view', 'group' => 'users'],
            ['name' => 'Suspend Users', 'slug' => 'users.suspend', 'group' => 'users'],
            ['name' => 'Block Users', 'slug' => 'users.block', 'group' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'group' => 'users'],
            ['name' => 'Verify Users', 'slug' => 'users.verify', 'group' => 'users'],
            ['name' => 'View Listings', 'slug' => 'listings.view', 'group' => 'listings'],
            ['name' => 'Moderate Listings', 'slug' => 'listings.moderate', 'group' => 'listings'],
            ['name' => 'Block Listings', 'slug' => 'listings.block', 'group' => 'listings'],
            ['name' => 'Delete Listings', 'slug' => 'listings.delete', 'group' => 'listings'],
            ['name' => 'View Categories', 'slug' => 'categories.view', 'group' => 'categories'],
            ['name' => 'Manage Categories', 'slug' => 'categories.manage', 'group' => 'categories'],
            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Assign Reports', 'slug' => 'reports.assign', 'group' => 'reports'],
            ['name' => 'Resolve Reports', 'slug' => 'reports.resolve', 'group' => 'reports'],
            ['name' => 'View Moderation Actions', 'slug' => 'moderation.actions.view', 'group' => 'moderation'],
            ['name' => 'View Settings', 'slug' => 'settings.view', 'group' => 'settings'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'group' => 'settings'],
            ['name' => 'View Audit Logs', 'slug' => 'audit.view', 'group' => 'audit'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'group' => 'roles'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                $permission,
            );
        }

        $roles = [
            'user' => [
                'name' => 'User',
                'description' => 'Registered marketplace user.',
                'permissions' => [],
            ],
            'moderator' => [
                'name' => 'Moderator',
                'description' => 'Content moderation access.',
                'permissions' => [
                    'users.view',
                    'listings.view',
                    'listings.moderate',
                    'listings.block',
                    'categories.view',
                    'reports.view',
                    'reports.assign',
                    'reports.resolve',
                    'moderation.actions.view',
                ],
            ],
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Platform administration access.',
                'permissions' => [
                    'users.view',
                    'users.suspend',
                    'users.block',
                    'users.delete',
                    'users.verify',
                    'listings.view',
                    'listings.moderate',
                    'listings.block',
                    'listings.delete',
                    'categories.view',
                    'categories.manage',
                    'reports.view',
                    'reports.assign',
                    'reports.resolve',
                    'moderation.actions.view',
                    'settings.view',
                    'settings.manage',
                    'audit.view',
                ],
            ],
            'super_admin' => [
                'name' => 'Super Administrator',
                'description' => 'Full platform access including role management.',
                'permissions' => Permission::query()->pluck('slug')->all(),
            ],
        ];

        foreach ($roles as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                ],
            );

            $permissionIds = Permission::query()
                ->whereIn('slug', $definition['permissions'])
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
