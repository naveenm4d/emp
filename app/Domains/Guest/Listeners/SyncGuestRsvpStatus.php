<?php

namespace App\Domains\Guest\Listeners;

use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Events\RsvpResponded;
use App\Domains\Rsvp\Events\RsvpSent;

/**
 * Keeps the guest's RSVP status in step with their RSVP link, and stores the
 * details they gave with their answer.
 */
class SyncGuestRsvpStatus
{
    public function __construct(
        private readonly GuestServiceInterface $guests,
    ) {}

    public function handle(RsvpSent|RsvpResponded $event): void
    {
        $status = match (true) {
            $event instanceof RsvpSent => GuestRsvpStatus::Pending,
            $event->rsvp->status === RsvpStatus::Accepted => GuestRsvpStatus::Confirmed,
            $event->rsvp->status === RsvpStatus::Maybe => GuestRsvpStatus::Maybe,
            default => GuestRsvpStatus::Declined,
        };

        $this->guests->setRsvpStatus($event->rsvp->guest_id, $status);

        if ($event instanceof RsvpResponded && $event->details !== null) {
            $this->guests->saveRegistrationDetails($event->rsvp->guest_id, $event->details);
        }
    }
}
