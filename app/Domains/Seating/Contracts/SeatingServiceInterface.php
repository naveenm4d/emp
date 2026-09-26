<?php

namespace App\Domains\Seating\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\DTOs\TableData;
use App\Domains\Seating\Enums\SeatMode;
use App\Domains\Seating\Models\EventTable;

/**
 * Tables and who sits where. A guest's party (plus-ones, children) always
 * sits together at one table, and nobody is seated twice.
 */
interface SeatingServiceInterface
{
    public function createTable(Event $event, TableData $data): EventTable;

    /** Refuses to remove seats someone sits in. */
    public function updateTable(EventTable $table, TableData $data): EventTable;

    /** Frees its seats. */
    public function deleteTable(EventTable $table): void;

    /**
     * Seats the guest's party at the table, from the given (free) seat on
     * through the next free seats. By mode:
     *  - Party: the whole party moves here, or nothing changes (TableFullException);
     *  - Split: the party is re-seated here as far as it fits, the rest wait for seats;
     *  - Rest:  only the party members without a seat are seated here, as far as they fit.
     *
     * @return int how many people were seated
     */
    public function assign(Guest $guest, EventTable $table, int $seatNumber, SeatMode $mode = SeatMode::Party): int;

    /** Seats or moves one person of the party (0 = the guest). */
    public function assignMember(Guest $guest, int $partyMember, EventTable $table, int $seatNumber): void;

    public function unassign(Guest $guest): void;

    /** Frees one person's seat; the rest of the party stays. */
    public function unassignMember(Guest $guest, int $partyMember): void;

    /** The two parties change places. Rolled back when either doesn't fit. */
    public function swap(Guest $guest, Guest $other): void;

    /** Frees the seated guest's party and seats the other guest there. */
    public function replace(Guest $seated, Guest $other): void;

    /**
     * Seats confirmed guests who have no seat at the first table with room.
     *
     * @return array{seated: int, unplaced: int}
     */
    public function autoSeat(Event $event): array;

    /** Follows a changed party size: frees seats, or takes free ones at the same table. */
    public function syncParty(Guest $guest): void;
}
