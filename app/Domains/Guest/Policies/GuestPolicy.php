<?php

namespace App\Domains\Guest\Policies;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Guest\Models\Guest;

/**
 * Client users see the guests of events they can see, and manage them with guests.manage.
 */
class GuestPolicy
{
    public function view(ClientUser $user, Guest $guest): bool
    {
        return $user->canAccessEvent($guest->event);
    }

    public function update(ClientUser $user, Guest $guest): bool
    {
        return $user->hasPermission(ClientPermission::GuestsManage) && $user->canAccessEvent($guest->event);
    }

    public function delete(ClientUser $user, Guest $guest): bool
    {
        return $this->update($user, $guest);
    }
}
