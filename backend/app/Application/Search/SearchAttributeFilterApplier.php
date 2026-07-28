<?php

namespace App\Application\Search;

use App\Domain\Category\Enums\AttributeType;
use App\Domain\Search\Exceptions\SearchException;
use App\Models\CategoryAttribute;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SearchAttributeFilterApplier
{
    /**
     * @param  Builder<Listing>  $query
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters, ?array $categoryScopeIds): void
    {
        if (empty($filters['attributes']) || ! is_array($filters['attributes'])) {
            return;
        }

        /** @var array<string, mixed> $attributeFilters */
        $attributeFilters = $filters['attributes'];
        $maxFilters = (int) config('search.max_attribute_filters', 20);

        if (count($attributeFilters) > $maxFilters) {
            throw new SearchException(
                errorCode: 'search.too_many_attribute_filters',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ['attr' => ['search.too_many_attribute_filters']],
            );
        }

        foreach ($attributeFilters as $slug => $value) {
            if (! is_string($slug) || $slug === '') {
                throw new SearchException(
                    errorCode: 'search.invalid_attribute_filter',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ['attr' => ['search.invalid_attribute_filter']],
                );
            }

            $attribute = $this->resolveFilterableAttribute($slug, $categoryScopeIds);

            if ($attribute === null) {
                throw new SearchException(
                    errorCode: 'search.invalid_attribute_filter',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ["attr.{$slug}" => ['search.invalid_attribute_filter']],
                );
            }

            $normalizedValue = $this->normalizeFilterValue($attribute, $value);

            $query->whereExists(function ($subQuery) use ($attribute, $normalizedValue, $categoryScopeIds): void {
                $subQuery->select(DB::raw('1'))
                    ->from('listing_attribute_values as lav')
                    ->join('category_attributes as ca', 'ca.id', '=', 'lav.category_attribute_id')
                    ->whereColumn('lav.listing_id', 'listings.id')
                    ->whereColumn('ca.category_id', 'listings.category_id')
                    ->where('lav.category_attribute_id', $attribute->id)
                    ->where('ca.filterable', true);

                if ($categoryScopeIds !== null) {
                    $subQuery->whereIn('ca.category_id', $categoryScopeIds);
                }

                $this->applyValueMatch($subQuery, $attribute->type, $normalizedValue);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function rejectDuplicateSlugs(array $attributes): array
    {
        $slugCounts = [];

        foreach (array_keys($attributes) as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $slugCounts[$slug] = ($slugCounts[$slug] ?? 0) + 1;
        }

        foreach ($slugCounts as $slug => $count) {
            if ($count > 1) {
                throw new SearchException(
                    errorCode: 'search.duplicate_attribute_filter',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ["attr.{$slug}" => ['search.duplicate_attribute_filter']],
                );
            }
        }

        return $attributes;
    }

    /**
     * @param  list<string>|null  $categoryScopeIds
     */
    private function resolveFilterableAttribute(string $slug, ?array $categoryScopeIds): ?CategoryAttribute
    {
        $query = CategoryAttribute::query()
            ->where('slug', $slug)
            ->where('filterable', true)
            ->with('options');

        if ($categoryScopeIds !== null) {
            $query->whereIn('category_id', $categoryScopeIds);
        }

        return $query->first();
    }

    private function normalizeFilterValue(CategoryAttribute $attribute, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            throw new SearchException(
                errorCode: 'search.invalid_attribute_filter',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ["attr.{$attribute->slug}" => ['search.invalid_attribute_filter']],
            );
        }

        return match ($attribute->type) {
            AttributeType::Boolean => $this->normalizeBoolean($attribute->slug, $value),
            AttributeType::Number, AttributeType::Price => $this->normalizeNumber($attribute, $value),
            AttributeType::Date => $this->normalizeDate($attribute->slug, $value),
            AttributeType::Dropdown, AttributeType::Radio => $this->normalizeSelectValue($attribute, $value, false),
            AttributeType::MultiSelect, AttributeType::Checkbox => $this->normalizeSelectValue($attribute, $value, true),
            AttributeType::Text, AttributeType::LongText => $this->normalizeText($attribute->slug, $value),
        };
    }

    private function normalizeBoolean(string $slug, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, ['true', '1', 1, 'yes'], true)) {
            return true;
        }

        if (in_array($value, ['false', '0', 0, 'no'], true)) {
            return false;
        }

        throw new SearchException(
            errorCode: 'search.invalid_attribute_filter',
            message: 'Validation failed.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            errors: ["attr.{$slug}" => ['search.invalid_attribute_filter']],
        );
    }

    private function normalizeNumber(CategoryAttribute $attribute, mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new SearchException(
                errorCode: 'search.invalid_attribute_filter',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ["attr.{$attribute->slug}" => ['search.invalid_attribute_filter']],
            );
        }

        $numeric = (float) $value;

        if ($attribute->min_value !== null && $numeric < (float) $attribute->min_value) {
            throw new SearchException(
                errorCode: 'search.invalid_attribute_filter',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ["attr.{$attribute->slug}" => ['search.invalid_attribute_filter']],
            );
        }

        if ($attribute->max_value !== null && $numeric > (float) $attribute->max_value) {
            throw new SearchException(
                errorCode: 'search.invalid_attribute_filter',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ["attr.{$attribute->slug}" => ['search.invalid_attribute_filter']],
            );
        }

        return (string) $value;
    }

    private function normalizeDate(string $slug, mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new SearchException(
                errorCode: 'search.invalid_attribute_filter',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ["attr.{$slug}" => ['search.invalid_attribute_filter']],
            );
        }

        return $value;
    }

    private function normalizeText(string $slug, mixed $value): string
    {
        if (! is_scalar($value)) {
            throw new SearchException(
                errorCode: 'search.invalid_attribute_filter',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ["attr.{$slug}" => ['search.invalid_attribute_filter']],
            );
        }

        return (string) $value;
    }

    private function normalizeSelectValue(CategoryAttribute $attribute, mixed $value, bool $allowMultiple): mixed
    {
        $allowed = $attribute->options->pluck('value')->all();

        if ($allowMultiple) {
            if (! is_array($value)) {
                throw new SearchException(
                    errorCode: 'search.invalid_attribute_filter',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ["attr.{$attribute->slug}" => ['search.invalid_attribute_filter']],
                );
            }

            foreach ($value as $item) {
                if (! in_array((string) $item, $allowed, true)) {
                    throw new SearchException(
                        errorCode: 'search.invalid_attribute_filter',
                        message: 'Validation failed.',
                        status: Response::HTTP_UNPROCESSABLE_ENTITY,
                        errors: ["attr.{$attribute->slug}" => ['search.invalid_attribute_filter']],
                    );
                }
            }

            return array_values(array_map(strval(...), $value));
        }

        if (! in_array((string) $value, $allowed, true)) {
            throw new SearchException(
                errorCode: 'search.invalid_attribute_filter',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: ["attr.{$attribute->slug}" => ['search.invalid_attribute_filter']],
            );
        }

        return (string) $value;
    }

    private function applyValueMatch($subQuery, AttributeType $type, mixed $value): void
    {
        match ($type) {
            AttributeType::Boolean => $subQuery->where('lav.value_boolean', $value),
            AttributeType::Number, AttributeType::Price => $subQuery->where('lav.value_number', $value),
            AttributeType::Date => $subQuery->whereDate('lav.value_date', $value),
            AttributeType::MultiSelect, AttributeType::Checkbox => $subQuery->where(function ($query) use ($value): void {
                foreach ((array) $value as $item) {
                    $query->whereJsonContains('lav.value_json', $item);
                }
            }),
            default => $subQuery->where('lav.value_text', $value),
        };
    }
}
