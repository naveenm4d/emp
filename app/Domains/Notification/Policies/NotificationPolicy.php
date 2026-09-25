<?php

namespace App\Domains\Notification\Policies;

use App\Domains\Client\Models\Client;
use App\Domains\Notification\Models\Notification;

class NotificationPolicy
{
    public function view(Client $client, Notification $notification): bool
    {
        return $notification->event->isOwnedBy($client);
    }
}
