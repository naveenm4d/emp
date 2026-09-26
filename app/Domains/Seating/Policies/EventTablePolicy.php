<?php

namespace App\Domains\Seating\Policies;

use App\Domains\Client\Models\Client;
use App\Domains\Seating\Models\EventTable;

/**
 * A client arranges the tables of events it owns.
 */
class EventTablePolicy
{
    public function update(Client $client, EventTable $table): bool
    {
        return $table->event->isOwnedBy($client);
    }

    public function delete(Client $client, EventTable $table): bool
    {
        return $table->event->isOwnedBy($client);
    }
}
