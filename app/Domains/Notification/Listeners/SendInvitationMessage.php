<?php

namespace App\Domains\Notification\Listeners;

use App\Domains\Invitation\Events\InvitationSent;
use App\Domains\Notification\Contracts\NotificationDispatcherInterface;

/**
 * Sends the invitation link to the guest over WhatsApp.
 */
class SendInvitationMessage
{
    public function __construct(
        private readonly NotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(InvitationSent $event): void
    {
        $invitation = $event->invitation->loadMissing(['event', 'guest']);

        $this->dispatcher->whatsapp(
            eventId: $invitation->event_id,
            guestId: $invitation->guest_id,
            phone: (string) $invitation->guest->phone,
            message: sprintf(
                "Hi %s, you're invited to %s! Please RSVP here: %s",
                $invitation->guest->name,
                $invitation->event->title,
                $invitation->rsvpUrl(),
            ),
        );
    }
}
