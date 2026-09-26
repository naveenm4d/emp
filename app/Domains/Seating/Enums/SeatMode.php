<?php

namespace App\Domains\Seating\Enums;

use App\Core\Enums\Concerns\HasValues;

/** How seating a guest treats their party (plus-ones, children). */
enum SeatMode: string
{
    use HasValues;

    /** The whole party sits together at the table, or nothing changes. */
    case Party = 'party';

    /** The party is re-seated here as far as it fits; the rest wait for seats elsewhere. */
    case Split = 'split';

    /** Only the party members still without a seat are seated here, as far as they fit. */
    case Rest = 'rest';

    /** One person of the party is seated or moved. */
    case Member = 'member';
}
