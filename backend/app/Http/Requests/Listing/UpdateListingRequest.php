<?php

namespace App\Http\Requests\Listing;

use App\Domain\Listing\Enums\ListingCondition;
use App\Domain\Listing\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateListingRequest extends FormRequest
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
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'city_id' => ['sometimes', 'uuid', 'exists:cities,id'],
            'district_id' => ['nullable', 'uuid', 'exists:districts,id'],
            'title' => ['sometimes', 'string', 'min:10', 'max:120'],
            'description' => ['sometimes', 'string', 'min:50', 'max:5000'],
            'price_type' => ['sometimes', Rule::enum(PriceType::class)],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'condition' => ['nullable', Rule::enum(ListingCondition::class)],
            'contact_preferences' => ['sometimes', 'array'],
            'contact_preferences.in_app' => ['sometimes', 'boolean'],
            'contact_preferences.phone' => ['sometimes', 'boolean'],
            'contact_preferences.whatsapp' => ['sometimes', 'boolean'],
            'contact_preferences.email' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attributes' => ['sometimes', 'array'],
            'attributes.*.slug' => ['required_with:attributes', 'string'],
            'attributes.*.value' => ['nullable'],
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
            'published_at' => ['prohibited'],
            'expires_at' => ['prohibited'],
            'moderation_notes' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
            'version' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
