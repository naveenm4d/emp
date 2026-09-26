<?php

namespace App\Domains\Guest\Events;

use App\Domains\Guest\Models\Guest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when the number of plus-ones or children coming with a guest
 * changes (the client's invitation or the guest's RSVP answer).
 */
final class GuestPartyChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Guest $guest,
    ) {}
}
