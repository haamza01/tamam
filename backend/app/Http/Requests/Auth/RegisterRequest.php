<?php

namespace App\Http\Requests\Auth;

use App\Application\Auth\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            try {
                $this->merge([
                    'phone' => app(PhoneNormalizer::class)->normalize((string) $this->input('phone')),
                ]);
            } catch (\InvalidArgumentException) {
                // Leave phone as-is so validation can surface a field error.
            }
        }

        if ($this->filled('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
