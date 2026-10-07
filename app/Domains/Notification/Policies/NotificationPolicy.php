<?php

namespace App\Domains\Notification\Policies;

use App\Domains\Client\Models\ClientUser;
use App\Domains\Notification\Models\Notification;

/**
 * Client users see the messages of events they can see.
 */
class NotificationPolicy
{
    public function view(ClientUser $user, Notification $notification): bool
    {
        return $user->canAccessEvent($notification->event);
    }
}
