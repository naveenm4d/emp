<?php

namespace App\Domains\Event\Enums;

use App\Core\Enums\Concerns\HasValues;

/** The input a custom registration question uses. */
enum QuestionType: string
{
    use HasValues;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';

    /** The client lists the choices (select, multi-select, radio). */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect, self::Radio], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Short text',
            self::Textarea => 'Long text',
            self::Number => 'Number',
            self::Select => 'Dropdown',
            self::MultiSelect => 'Multiple choice',
            self::Radio => 'Single choice',
            self::Checkbox => 'Yes / no',
        };
    }
}
