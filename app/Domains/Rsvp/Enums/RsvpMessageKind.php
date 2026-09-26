<?php

namespace App\Domains\Rsvp\Enums;

/** Which WhatsApp text an RSVP message uses: the invitation with the link, or a reminder to reply. */
enum RsvpMessageKind: string
{
    case Invitation = 'invitation';
    case Reminder = 'reminder';
}
