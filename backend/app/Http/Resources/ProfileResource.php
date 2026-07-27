<?php

namespace App\Http\Resources;

use App\Application\Profile\AvatarStorageService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Authenticated own-profile resource.
 * Public seller profiles (Phase 1D+) must use a separate resource that masks phone/email.
 *
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AvatarStorageService $avatars */
        $avatars = app(AvatarStorageService::class);

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferred_language' => $this->language->value,
            'bio' => $this->bio,
            'avatar_url' => $avatars->resolveUrl($this->avatar),
            'phone_verified' => $this->isPhoneVerified(),
            'member_since' => $this->created_at?->toIso8601String(),
        ];
    }
}
