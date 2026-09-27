<?php

namespace App\Domains\Seating\Policies;

use App\Domains\Client\Models\Client;
use App\Domains\Seating\Models\VenueElement;

/**
 * A client arranges the floor plan of events it owns.
 */
class VenueElementPolicy
{
    public function update(Client $client, VenueElement $element): bool
    {
        return $element->event->isOwnedBy($client);
    }

    public function delete(Client $client, VenueElement $element): bool
    {
        return $element->event->isOwnedBy($client);
    }
}
