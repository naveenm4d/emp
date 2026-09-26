<?php

namespace App\Domains\Event\Enums;

use App\Core\Enums\Concerns\HasValues;

/** Dietary restrictions a guest can pick when the event collects them. */
enum DietaryOption: string
{
    use HasValues;

    case None = 'none';
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Halal = 'halal';
    case GlutenFree = 'gluten_free';
    case DairyFree = 'dairy_free';
    case Other = 'other';
}
