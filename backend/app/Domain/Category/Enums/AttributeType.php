<?php

namespace App\Domain\Category\Enums;

enum AttributeType: string
{
    case Text = 'text';
    case LongText = 'long_text';
    case Number = 'number';
    case Price = 'price';
    case Dropdown = 'dropdown';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Boolean = 'boolean';
    case Date = 'date';
    case MultiSelect = 'multi_select';

    public function isSelectType(): bool
    {
        return in_array($this, [self::Dropdown, self::Radio, self::MultiSelect, self::Checkbox], true);
    }

    public function allowsMultipleValues(): bool
    {
        return in_array($this, [self::MultiSelect, self::Checkbox], true);
    }
}
