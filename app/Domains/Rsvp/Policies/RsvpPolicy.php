<?php

namespace App\Domains\Rsvp\Policies;

use App\Domains\Client\Models\Client;
use App\Domains\Rsvp\Models\Rsvp;

class RsvpPolicy
{
    public function view(Client $client, Rsvp $rsvp): bool
    {
        return $rsvp->event->isOwnedBy($client);
    }

    public function update(Client $client, Rsvp $rsvp): bool
    {
        return $rsvp->event->isOwnedBy($client);
    }
}
