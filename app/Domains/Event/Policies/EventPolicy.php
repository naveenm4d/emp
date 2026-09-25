<?php

namespace App\Domains\Event\Policies;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;

/**
 * Client ownership rules. Staff access is controlled by staff permissions.
 */
class EventPolicy
{
    public function view(Client $client, Event $event): bool
    {
        return $event->isOwnedBy($client);
    }

    public function update(Client $client, Event $event): bool
    {
        return $event->isOwnedBy($client);
    }

    public function delete(Client $client, Event $event): bool
    {
        return $event->isOwnedBy($client);
    }
}
