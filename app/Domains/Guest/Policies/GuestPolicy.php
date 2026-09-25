<?php

namespace App\Domains\Guest\Policies;

use App\Domains\Client\Models\Client;
use App\Domains\Guest\Models\Guest;

/**
 * A client manages the guests of events it owns.
 */
class GuestPolicy
{
    public function view(Client $client, Guest $guest): bool
    {
        return $guest->event->isOwnedBy($client);
    }

    public function update(Client $client, Guest $guest): bool
    {
        return $guest->event->isOwnedBy($client);
    }

    public function delete(Client $client, Guest $guest): bool
    {
        return $guest->event->isOwnedBy($client);
    }
}
