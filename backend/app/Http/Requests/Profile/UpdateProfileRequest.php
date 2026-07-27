<?php

namespace App\Http\Requests\Profile;

use App\Domain\User\Enums\UserLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'full_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'preferred_language' => ['sometimes', Rule::enum(UserLanguage::class)],
            'username' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('users', 'username')->ignore($userId)],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'profile.email_taken',
            'username.unique' => 'profile.username_taken',
        ];
    }
}
