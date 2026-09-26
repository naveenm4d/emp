<?php

namespace App\Domains\Seating\Enums;

use App\Core\Enums\Concerns\HasValues;

/** How a table is drawn on the seating plan, with its seats around it. */
enum TableShape: string
{
    use HasValues;

    case Round = 'round';
    case Oval = 'oval';
    case Square = 'square';
    case Rectangle = 'rectangle';
}
