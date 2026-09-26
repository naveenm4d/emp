<?php

namespace App\Domains\Rsvp\Events;

use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when an RSVP link is sent or re-sent. The Notification domain
 * delivers the message; the Guest domain marks the RSVP as pending.
 */
final class RsvpSent
{
    use Dispatchable;

    public function __construct(
        public readonly Rsvp $rsvp,
    ) {}
}
