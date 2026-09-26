<?php

namespace App\Domains\Seating\Listeners;

use App\Domains\Guest\Events\GuestPartyChanged;
use App\Domains\Seating\Contracts\SeatingServiceInterface;

/**
 * Keeps a seated party's seats in step with its size.
 */
class SyncGuestSeats
{
    public function __construct(
        private readonly SeatingServiceInterface $seating,
    ) {}

    public function handle(GuestPartyChanged $event): void
    {
        $this->seating->syncParty($event->guest);
    }
}
