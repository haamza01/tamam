<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AuthUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->maskPhone($this->phone),
            'email' => $this->email,
            'language' => $this->language->value,
            'status' => $this->status->value,
            'verification_level' => $this->verification_level->value,
            'phone_verified' => $this->isPhoneVerified(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('slug')->values()),
        ];
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 8) {
            return $phone;
        }

        return substr($phone, 0, 4).'****'.substr($phone, -2);
    }
}
