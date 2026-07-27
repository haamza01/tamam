<?php

namespace App\Application\Listing;

use App\Domain\Category\Enums\AttributeType;
use App\Domain\Listing\Exceptions\ListingException;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Listing;
use Symfony\Component\HttpFoundation\Response;

class ListingAttributeValidator
{
    /**
     * @param  array<int, array{slug: string, value: mixed}>|null  $attributes
     * @return array<string, array<string, mixed>>
     */
    public function validateAndNormalize(Category $category, ?array $attributes, bool $requireAllRequired): array
    {
        $definitions = CategoryAttribute::query()
            ->where('category_id', $category->id)
            ->with(['options', 'translations'])
            ->orderBy('sort_order')
            ->get()
            ->keyBy('slug');

        $input = collect($attributes ?? [])->values();
        $slugCounts = $input->pluck('slug')->countBy();

        foreach ($slugCounts as $slug => $count) {
            if ($count > 1) {
                throw new ListingException(
                    errorCode: 'listing.attribute_validation_failed',
                    message: 'Validation failed.',
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    errors: ["attributes.{$slug}" => ['listing.attribute_duplicate']],
                );
            }
        }

        $input = $input->keyBy('slug');
        $normalized = [];
        $errors = [];

        foreach ($definitions as $slug => $definition) {
            $provided = $input->get($slug);
            $value = is_array($provided) ? ($provided['value'] ?? null) : null;
            $hasValue = ! $this->isEmptyValue($value, $definition->type);

            if ($definition->required && $requireAllRequired && ! $hasValue) {
                $errors["attributes.{$slug}"] = ['listing.attribute_required'];

                continue;
            }

            if (! $hasValue) {
                continue;
            }

            try {
                $normalized[$definition->id] = $this->normalizeValue($definition, $value);
            } catch (ListingException $exception) {
                $errors["attributes.{$slug}"] = $exception->errors()['value'] ?? ['listing.attribute_invalid'];
            }
        }

        foreach ($input as $slug => $payload) {
            if (! $definitions->has($slug)) {
                $errors["attributes.{$slug}"] = ['listing.attribute_not_allowed'];
            }
        }

        if ($errors !== []) {
            throw new ListingException(
                errorCode: 'listing.attribute_validation_failed',
                message: 'Validation failed.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                errors: $errors,
            );
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, mixed>>  $normalized
     */
    public function syncValues(Listing $listing, array $normalized): void
    {
        $listing->attributeValues()->delete();

        foreach ($normalized as $attributeId => $columns) {
            $listing->attributeValues()->create(array_merge(
                ['category_attribute_id' => $attributeId],
                $columns,
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeValue(CategoryAttribute $definition, mixed $value): array
    {
        return match ($definition->type) {
            AttributeType::Text, AttributeType::LongText => ['value_text' => $this->asString($value, 5000)],
            AttributeType::Number, AttributeType::Price => $this->numberColumns($definition, $value),
            AttributeType::Boolean => ['value_boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? throw $this->invalidValue()],
            AttributeType::Date => ['value_date' => $this->asDate($value)],
            AttributeType::Dropdown, AttributeType::Radio => $this->selectColumns($definition, $value, false),
            AttributeType::MultiSelect, AttributeType::Checkbox => $this->selectColumns($definition, $value, true),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function numberColumns(CategoryAttribute $definition, mixed $value): array
    {
        if (! is_numeric($value)) {
            throw $this->invalidValue();
        }

        $number = (float) $value;

        if ($definition->min_value !== null && $number < (float) $definition->min_value) {
            throw $this->invalidValue();
        }

        if ($definition->max_value !== null && $number > (float) $definition->max_value) {
            throw $this->invalidValue();
        }

        return ['value_number' => $number];
    }

    /**
     * @return array<string, mixed>
     */
    private function selectColumns(CategoryAttribute $definition, mixed $value, bool $multiple): array
    {
        $allowed = $definition->options->pluck('value')->all();
        $values = $multiple ? (array) $value : [$value];

        foreach ($values as $item) {
            if (! in_array((string) $item, $allowed, true)) {
                throw $this->invalidValue();
            }
        }

        if ($multiple) {
            return ['value_json' => array_values(array_map('strval', $values))];
        }

        return ['value_text' => (string) $values[0]];
    }

    private function asString(mixed $value, int $max): string
    {
        $string = trim((string) $value);

        if ($string === '' || strlen($string) > $max) {
            throw $this->invalidValue();
        }

        return $string;
    }

    private function asDate(mixed $value): string
    {
        $string = (string) $value;

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $string) !== 1) {
            throw $this->invalidValue();
        }

        return $string;
    }

    private function isEmptyValue(mixed $value, AttributeType $type): bool
    {
        if ($value === null) {
            return true;
        }

        if ($type->allowsMultipleValues()) {
            return ! is_array($value) || $value === [];
        }

        return trim((string) $value) === '';
    }

    private function invalidValue(): ListingException
    {
        return new ListingException(
            errorCode: 'listing.attribute_invalid',
            message: 'Validation failed.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            errors: ['value' => ['listing.attribute_invalid']],
        );
    }
}
