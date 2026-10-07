<?php

namespace App\Domains\Seating\Policies;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Seating\Models\VenueElement;

/**
 * Client users with seating.manage arrange the floor plan of events they can see.
 */
class VenueElementPolicy
{
    public function update(ClientUser $user, VenueElement $element): bool
    {
        return $user->hasPermission(ClientPermission::SeatingManage) && $user->canAccessEvent($element->event);
    }

    public function delete(ClientUser $user, VenueElement $element): bool
    {
        return $this->update($user, $element);
    }
}
