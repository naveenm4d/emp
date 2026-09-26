<?php

namespace App\Domains\Event\Enums;

use App\Core\Enums\Concerns\HasValues;

/** Whether the registration / RSVP form asks for a built-in field, and if so whether it must be filled. */
enum FieldRequirement: string
{
    use HasValues;

    case Off = 'off';
    case Optional = 'optional';
    case Required = 'required';

    public function isCollected(): bool
    {
        return $this !== self::Off;
    }
}
