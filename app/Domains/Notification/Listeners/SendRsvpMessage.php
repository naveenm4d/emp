<?php

namespace App\Domains\Notification\Listeners;

use App\Domains\Notification\Contracts\NotificationDispatcherInterface;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Rsvp\Events\RsvpSent;
use App\Domains\Rsvp\Support\RsvpMessage;

/**
 * Sends the RSVP link to the guest over WhatsApp, in the guest's or event's message.
 */
class SendRsvpMessage
{
    public function __construct(
        private readonly NotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(RsvpSent $event): void
    {
        $rsvp = $event->rsvp->loadMissing(['event', 'guest']);

        $this->dispatcher->whatsapp(
            eventId: $rsvp->event_id,
            guestId: $rsvp->guest_id,
            phone: (string) $rsvp->guest->phone,
            message: RsvpMessage::for($rsvp),
            rsvpId: $rsvp->id,
            kind: NotificationKind::RsvpInvitation,
        );
    }
}
