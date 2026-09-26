<?php

namespace App\Domains\Seating\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Database\Eloquent\Collection;

interface SeatingQueryServiceInterface
{
    /** @return Collection<int, EventTable> in order, with seats and seated guests */
    public function tables(Event $event): Collection;

    /** @return Collection<int, Guest> approved guests, with their seats */
    public function guests(Event $event): Collection;

    /**
     * Seats taken, confirmed people seated, and confirmed guests still
     * missing seats (for the "needs a seat" list).
     *
     * @param  Collection<int, EventTable>  $tables
     * @param  Collection<int, Guest>  $guests
     * @return array{seats_total: int, seats_taken: int, confirmed_people: int, confirmed_seated: int, unseated: list<array{guest_id: string, name: string, party_size: int, missing: int}>}
     */
    public function summary(Collection $tables, Collection $guests): array;
}
