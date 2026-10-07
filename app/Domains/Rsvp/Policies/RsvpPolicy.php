<?php

namespace App\Domains\Rsvp\Policies;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Rsvp\Models\Rsvp;

/**
 * Client users see the RSVP links of events they can see, and send them with messages.send.
 */
class RsvpPolicy
{
    public function view(ClientUser $user, Rsvp $rsvp): bool
    {
        return $user->canAccessEvent($rsvp->event);
    }

    public function update(ClientUser $user, Rsvp $rsvp): bool
    {
        return $user->hasPermission(ClientPermission::MessagesSend) && $user->canAccessEvent($rsvp->event);
    }
}
