<?php

namespace App\Domains\Guest\Enums;

use App\Core\Enums\Concerns\HasValues;

enum RsvpStatus: string
{
    use HasValues;

    case NotSent = 'not_sent';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
}
