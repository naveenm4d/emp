<?php

namespace App\Domains\Rsvp\Events;

use App\Domains\Guest\DTOs\RegistrationDetailsData;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a guest answers their RSVP link (accepted, declined or maybe),
 * or changes their answer. Carries the details they gave; null on decline.
 */
final class RsvpResponded
{
    use Dispatchable;

    public function __construct(
        public readonly Rsvp $rsvp,
        public readonly ?RegistrationDetailsData $details = null,
    ) {}
}
