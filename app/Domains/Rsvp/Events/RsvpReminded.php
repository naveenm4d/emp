<?php

namespace App\Domains\Rsvp\Events;

use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a guest is reminded to reply to their RSVP link, by the client
 * or automatically. The Notification domain delivers the reminder.
 */
final class RsvpReminded
{
    use Dispatchable;

    public function __construct(
        public readonly Rsvp $rsvp,
    ) {}
}
