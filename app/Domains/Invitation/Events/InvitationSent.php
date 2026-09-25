<?php

namespace App\Domains\Invitation\Events;

use App\Domains\Invitation\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when an invitation is sent or re-sent. The Notification domain
 * delivers the message; the Guest domain marks the RSVP as pending.
 */
final class InvitationSent
{
    use Dispatchable;

    public function __construct(
        public readonly Invitation $invitation,
    ) {}
}
