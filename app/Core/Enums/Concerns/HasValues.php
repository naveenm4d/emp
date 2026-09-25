<?php

namespace App\Core\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Helpers for string-backed enums.
 *
 * @mixin \BackedEnum
 */
trait HasValues
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return Str::headline($this->value);
    }

    /** @return list<array{value: string, label: string}> options for UI selects */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
