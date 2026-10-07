<?php

namespace App\Domains\Client\Enums;

use App\Core\Enums\Concerns\HasValues;

/** A user's place in a client account. */
enum ClientUserRole: string
{
    use HasValues;

    /** The account's first user (or whoever it was handed to): has every permission and every event. */
    case Owner = 'owner';
    /** Invited by the owner (or a user with team.manage), with the permissions and events they were given. */
    case Member = 'member';
}
