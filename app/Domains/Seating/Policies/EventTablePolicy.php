<?php

namespace App\Domains\Seating\Policies;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Seating\Models\EventTable;

/**
 * Client users with seating.manage arrange the tables of events they can see.
 */
class EventTablePolicy
{
    public function update(ClientUser $user, EventTable $table): bool
    {
        return $user->hasPermission(ClientPermission::SeatingManage) && $user->canAccessEvent($table->event);
    }

    public function delete(ClientUser $user, EventTable $table): bool
    {
        return $this->update($user, $table);
    }
}
