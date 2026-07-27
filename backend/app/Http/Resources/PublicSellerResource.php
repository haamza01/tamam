<?php

namespace App\Http\Resources;

use App\Application\Profile\AvatarStorageService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class PublicSellerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'avatar_url' => $this->avatar
                ? app(AvatarStorageService::class)->resolveUrl($this->avatar)
                : app(AvatarStorageService::class)->defaultUrl(),
            'member_since' => $this->created_at?->toIso8601String(),
            'trusted_seller' => $this->trusted_seller,
            'verification_level' => $this->verification_level->value,
        ];
    }
}
