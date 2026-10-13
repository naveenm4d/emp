<?php

namespace App\Domains\Seating\Enums;

use App\Core\Enums\Concerns\HasValues;

/** Where the reception happens, for the 3D seating view: a hall or a garden. */
enum VenueSetting: string
{
    use HasValues;

    case Indoor = 'indoor';
    case Outdoor = 'outdoor';
}
