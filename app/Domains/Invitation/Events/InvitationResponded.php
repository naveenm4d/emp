<?php

namespace App\Domains\Invitation\Events;

use App\Domains\Invitation\Models\Invitation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Raised when a guest accepts or declines an invitation.
 */
final class InvitationResponded
{
    use Dispatchable;

    public function __construct(
        public readonly Invitation $invitation,
    ) {}
}
