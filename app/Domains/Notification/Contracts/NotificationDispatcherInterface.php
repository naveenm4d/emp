<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Notification\Models\Notification;

/**
 * Entry point other domains use to send a message to a guest. Creates a
 * pending notification and queues it for delivery.
 */
interface NotificationDispatcherInterface
{
    public function whatsapp(string $eventId, ?string $guestId, string $phone, string $message): Notification;
}
