<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Notification\Models\Notification;

/**
 * Entry point other domains use to send a message to a guest. Creates a
 * pending notification and queues it for delivery.
 */
interface NotificationDispatcherInterface
{
    /**
     * @param  string|null  $rsvpId  the RSVP link the message is sent for, if any
     * @param  NotificationKind|null  $kind  what the message is (shown in the guest's timeline)
     */
    public function whatsapp(string $eventId, ?string $guestId, string $phone, string $message, ?string $rsvpId = null, ?NotificationKind $kind = null): Notification;
}
