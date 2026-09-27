<?php

namespace App\Domains\Event\Enums;

use App\Core\Enums\Concerns\HasValues;

/** Event lists split by date: today onwards (or not dated yet) vs. already held. */
enum EventPeriod: string
{
    use HasValues;

    case Upcoming = 'upcoming';
    case Past = 'past';
}
