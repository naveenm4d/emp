<?php

namespace App\Domains\Rsvp\Events;

use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a guest accepts or declines their RSVP link.
 */
final class RsvpResponded
{
    use Dispatchable;

    public function __construct(
        public readonly Rsvp $rsvp,
    ) {}
}
