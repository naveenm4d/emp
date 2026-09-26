<?php

namespace App\Domains\Notification\Enums;

use App\Core\Enums\Concerns\HasValues;

/** What a message was sent for; shown in the guest's message timeline. */
enum NotificationKind: string
{
    use HasValues;

    case RsvpInvitation = 'rsvp_invitation';
    case RsvpReminder = 'rsvp_reminder';
}
