<?php

namespace App\Http\Requests\Search;

use Illuminate\Foundation\Http\FormRequest;

class SearchSuggestionsRequest extends FormRequest
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
            'q' => ['required_without:keyword', 'string'],
            'keyword' => ['required_without:q', 'string'],
        ];
    }

    public function prefix(): string
    {
        return (string) ($this->input('q') ?? $this->input('keyword') ?? '');
    }
}
