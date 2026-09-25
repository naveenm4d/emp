<?php

namespace App\Domains\Guest\Enums;

use App\Core\Enums\Concerns\HasValues;

enum CheckInStatus: string
{
    use HasValues;

    case NotCheckedIn = 'not_checked_in';
    case CheckedIn = 'checked_in';
}
