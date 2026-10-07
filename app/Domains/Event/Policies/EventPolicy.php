<?php

namespace App\Domains\Event\Policies;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Models\Event;

/**
 * Client users see the account's events they were given (the owner sees
 * all) and act on them with their permissions. Staff access is controlled by
 * staff permissions.
 */
class EventPolicy
{
    public function create(ClientUser $user): bool
    {
        return $user->hasPermission(ClientPermission::EventsCreate);
    }

    public function view(ClientUser $user, Event $event): bool
    {
        return $user->canAccessEvent($event);
    }

    public function update(ClientUser $user, Event $event): bool
    {
        return $this->allows($user, $event, ClientPermission::EventsUpdate);
    }

    public function delete(ClientUser $user, Event $event): bool
    {
        return $this->allows($user, $event, ClientPermission::EventsDelete);
    }

    public function manageGuests(ClientUser $user, Event $event): bool
    {
        return $this->allows($user, $event, ClientPermission::GuestsManage);
    }

    public function sendMessages(ClientUser $user, Event $event): bool
    {
        return $this->allows($user, $event, ClientPermission::MessagesSend);
    }

    public function manageSeating(ClientUser $user, Event $event): bool
    {
        return $this->allows($user, $event, ClientPermission::SeatingManage);
    }

    private function allows(ClientUser $user, Event $event, ClientPermission $permission): bool
    {
        return $user->hasPermission($permission) && $user->canAccessEvent($event);
    }
}
