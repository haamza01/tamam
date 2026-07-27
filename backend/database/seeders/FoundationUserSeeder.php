<?php

namespace Database\Seeders;

use App\Domain\User\Enums\AccountStatus;
use App\Domain\User\Enums\UserLanguage;
use App\Domain\User\Enums\VerificationLevel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FoundationUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'full_name' => 'Super Admin',
                'email' => 'super@tamam.local',
                'phone' => '+97450000001',
                'role' => 'super_admin',
            ],
            [
                'full_name' => 'Platform Admin',
                'email' => 'admin@tamam.local',
                'phone' => '+97450000002',
                'role' => 'admin',
            ],
            [
                'full_name' => 'Platform Moderator',
                'email' => 'mod@tamam.local',
                'phone' => '+97450000003',
                'role' => 'moderator',
            ],
        ];

        foreach ($users as $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'full_name' => $definition['full_name'],
                    'phone' => $definition['phone'],
                    'password' => Hash::make('Password123!'),
                    'language' => UserLanguage::Arabic,
                    'status' => AccountStatus::Active,
                    'verification_level' => VerificationLevel::Phone,
                    'phone_verified_at' => now(),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$definition['role']]);
        }
    }
}
