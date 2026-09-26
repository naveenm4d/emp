<?php

namespace App\Domains\Notification\Listeners;

use App\Domains\Notification\Contracts\NotificationDispatcherInterface;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Rsvp\Enums\RsvpMessageKind;
use App\Domains\Rsvp\Events\RsvpReminded;
use App\Domains\Rsvp\Support\RsvpMessage;

/**
 * Sends the guest a reminder to reply to their RSVP link over WhatsApp, in the guest's or event's reminder text.
 */
class SendRsvpReminder
{
    public function __construct(
        private readonly NotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(RsvpReminded $event): void
    {
        $rsvp = $event->rsvp->loadMissing(['event', 'guest']);

        $this->dispatcher->whatsapp(
            eventId: $rsvp->event_id,
            guestId: $rsvp->guest_id,
            phone: (string) $rsvp->guest->phone,
            message: RsvpMessage::for($rsvp, RsvpMessageKind::Reminder),
            rsvpId: $rsvp->id,
            kind: NotificationKind::RsvpReminder,
        );
    }
}
