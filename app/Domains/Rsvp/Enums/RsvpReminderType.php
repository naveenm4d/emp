<?php

namespace App\Domains\Rsvp\Enums;

/** The automatic reminders an event can send; each goes out at most once per RSVP link. */
enum RsvpReminderType: string
{
    /** remind_after_days after the link was sent, if the guest has not replied. */
    case AfterSent = 'after_sent';

    /** remind_before_days before the event date. */
    case BeforeEvent = 'before_event';

    /** The rsvps column stamped when this reminder goes out. */
    public function column(): string
    {
        return match ($this) {
            self::AfterSent => 'auto_after_reminded_at',
            self::BeforeEvent => 'auto_before_reminded_at',
        };
    }
}
