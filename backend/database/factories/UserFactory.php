<?php

namespace Database\Factories;

use App\Domain\User\Enums\AccountStatus;
use App\Domain\User\Enums\UserLanguage;
use App\Domain\User\Enums\VerificationLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+9745'.fake()->unique()->numerify('#######'),
            'password' => static::$password ??= Hash::make('Password123!'),
            'language' => UserLanguage::Arabic,
            'status' => AccountStatus::Active,
            'verification_level' => VerificationLevel::None,
            'trusted_seller' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function phoneVerified(): static
    {
        return $this->state(fn (): array => [
            'phone_verified_at' => now(),
            'verification_level' => VerificationLevel::Phone,
        ]);
    }
}
