<?php

namespace App\Domains\Seating\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Seating\Models\SeatAssignment;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<SeatAssignment>
 */
interface SeatAssignmentRepositoryInterface extends RepositoryInterface
{
    /** @return Collection<int, SeatAssignment> the guest's party, by party member */
    public function forGuest(string $guestId): Collection;

    /** Seats held by guests of the event who confirmed (one per seated person). */
    public function countConfirmedForEvent(string $eventId): int;

    /** @return list<int> taken seat numbers at the table */
    public function takenSeats(string $tableId): array;

    public function releaseGuest(string $guestId): void;

    /** Frees one person of the party. */
    public function releaseMember(string $guestId, int $partyMember): void;

    /** Frees the party members from this position on (a smaller party). */
    public function releaseMembersFrom(string $guestId, int $partyMember): void;
}
