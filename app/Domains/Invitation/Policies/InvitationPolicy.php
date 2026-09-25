<?php

namespace App\Domains\Invitation\Policies;

use App\Domains\Client\Models\Client;
use App\Domains\Invitation\Models\Invitation;

class InvitationPolicy
{
    public function view(Client $client, Invitation $invitation): bool
    {
        return $invitation->event->isOwnedBy($client);
    }

    public function update(Client $client, Invitation $invitation): bool
    {
        return $invitation->event->isOwnedBy($client);
    }
}
