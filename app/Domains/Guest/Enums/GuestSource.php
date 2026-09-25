<?php

namespace App\Domains\Guest\Enums;

use App\Core\Enums\Concerns\HasValues;

/** How a guest entered the list. Always set by the server. */
enum GuestSource: string
{
    use HasValues;

    case Manual = 'manual';
    case PublicLink = 'public_link';
    case Import = 'import';
    case Api = 'api';
}
