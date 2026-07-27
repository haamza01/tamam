<?php

namespace App\Http\Requests\Search;

use App\Domain\Listing\Enums\ListingCondition;
use App\Domain\Listing\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchListingsRequest extends FormRequest
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
            'keyword' => ['nullable', 'string'],
            'category' => ['nullable', 'uuid'],
            'category_id' => ['nullable', 'uuid'],
            'city' => ['nullable', 'uuid'],
            'city_id' => ['nullable', 'uuid'],
            'district' => ['nullable', 'uuid'],
            'district_id' => ['nullable', 'uuid'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'price_type' => ['nullable', Rule::enum(PriceType::class)],
            'condition' => ['nullable', Rule::enum(ListingCondition::class)],
            'sort' => ['nullable', 'string', Rule::in(['relevance', 'newest', 'oldest', 'price_asc', 'price_desc', 'most_viewed', 'latest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.config('search.pagination.max')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('search.pagination.max')],
            'attr' => ['nullable', 'array', 'max:20'],
            'attr.*' => ['nullable'],
            'attributes' => ['nullable', 'array', 'max:20'],
            'attributes.*' => ['nullable'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $attributes = array_merge(
            $this->input('attr', []) ?? [],
            $this->input('attributes', []) ?? [],
        );

        $condition = $this->input('condition');
        $priceType = $this->input('price_type');

        return [
            'keyword' => $this->input('keyword'),
            'category_id' => $this->input('category_id') ?? $this->input('category'),
            'city_id' => $this->input('city_id') ?? $this->input('city'),
            'district_id' => $this->input('district_id') ?? $this->input('district'),
            'price_min' => $this->input('price_min'),
            'price_max' => $this->input('price_max'),
            'price_type' => $priceType instanceof PriceType ? $priceType->value : $priceType,
            'condition' => $condition instanceof ListingCondition ? $condition->value : $condition,
            'sort' => $this->input('sort'),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('limit') ?? $this->input('per_page'),
            'attributes' => is_array($attributes) ? $attributes : [],
        ];
    }
}
