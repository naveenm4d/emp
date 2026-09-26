<?php

namespace App\Domains\Guest\Listeners;

use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Events\EventRegistrationTypeChanged;
use App\Domains\Guest\Contracts\GuestServiceInterface;

/**
 * Guest-list-only events have no approval: everyone on the list is approved,
 * so guests left pending, waitlisted or rejected can still be invited.
 */
class ApproveGuestListGuests
{
    public function __construct(
        private readonly GuestServiceInterface $guests,
    ) {}

    public function handle(EventRegistrationTypeChanged $event): void
    {
        if ($event->event->registration_type === RegistrationType::GuestListOnly) {
            $this->guests->approveEveryone($event->event);
        }
    }
}
