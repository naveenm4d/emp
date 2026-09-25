<?php

namespace App\Domains\Guest\Listeners;

use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Enums\RsvpStatus;
use App\Domains\Invitation\Enums\InvitationStatus;
use App\Domains\Invitation\Events\InvitationResponded;
use App\Domains\Invitation\Events\InvitationSent;

/**
 * Keeps the guest's RSVP status in step with their invitation.
 */
class SyncGuestRsvpStatus
{
    public function __construct(
        private readonly GuestServiceInterface $guests,
    ) {}

    public function handle(InvitationSent|InvitationResponded $event): void
    {
        $status = match (true) {
            $event instanceof InvitationSent => RsvpStatus::Pending,
            $event->invitation->status === InvitationStatus::Accepted => RsvpStatus::Confirmed,
            default => RsvpStatus::Declined,
        };

        $this->guests->setRsvpStatus($event->invitation->guest_id, $status);
    }
}
