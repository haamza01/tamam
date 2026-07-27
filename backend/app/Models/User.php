<?php

namespace App\Models;

use App\Domain\Shared\Concerns\HasUuid;
use App\Domain\User\Enums\AccountStatus;
use App\Domain\User\Enums\AccountType;
use App\Domain\User\Enums\UserLanguage;
use App\Domain\User\Enums\VerificationLevel;
use App\Models\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'full_name',
        'username',
        'email',
        'phone',
        'password',
        'avatar',
        'bio',
        'language',
        'country_id',
        'city_id',
        'account_type',
        'verification_level',
        'status',
        'phone_verified_at',
        'email_verified_at',
        'trusted_seller',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'language' => UserLanguage::class,
            'account_type' => AccountType::class,
            'verification_level' => VerificationLevel::class,
            'status' => AccountStatus::class,
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'trusted_seller' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isActiveAccount(): bool
    {
        return in_array($this->status, [AccountStatus::Pending, AccountStatus::Active, AccountStatus::Suspended], true);
    }
}
