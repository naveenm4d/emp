<?php

namespace App\Domains\Seating\Services;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\Contracts\EventTableRepositoryInterface;
use App\Domains\Seating\Contracts\SeatAssignmentRepositoryInterface;
use App\Domains\Seating\Contracts\SeatingQueryServiceInterface;
use App\Domains\Seating\Contracts\VenueElementRepositoryInterface;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Database\Eloquent\Collection;

class SeatingQueryService implements SeatingQueryServiceInterface
{
    public function __construct(
        private readonly EventTableRepositoryInterface $tables,
        private readonly GuestQueryServiceInterface $guests,
        private readonly SeatAssignmentRepositoryInterface $seats,
        private readonly VenueElementRepositoryInterface $elements,
    ) {}

    public function confirmedSeatedCount(Event $event): int
    {
        return $this->seats->countConfirmedForEvent($event->id);
    }

    public function tables(Event $event): Collection
    {
        return $this->tables->forEvent($event->id);
    }

    public function venueElements(Event $event): Collection
    {
        return $this->elements->forEvent($event->id);
    }

    public function guests(Event $event): Collection
    {
        return $this->guests->approvedWithSeats($event);
    }

    public function summary(Collection $tables, Collection $guests): array
    {
        $confirmed = $guests->filter(fn (Guest $guest) => $guest->rsvp_status === GuestRsvpStatus::Confirmed);

        return [
            'seats_total' => (int) $tables->sum('seat_count'),
            'seats_taken' => $tables->sum(fn (EventTable $table) => $table->seats->count()),
            'confirmed_people' => $confirmed->sum(fn (Guest $guest) => $guest->partySize()),
            'confirmed_seated' => $confirmed->sum(fn (Guest $guest) => $guest->seats->count()),
            'unseated' => array_values($confirmed
                ->filter(fn (Guest $guest) => $guest->seats->count() < $guest->partySize())
                ->map(fn (Guest $guest) => [
                    'guest_id' => $guest->id,
                    'name' => $guest->name,
                    'party_size' => $guest->partySize(),
                    'missing' => $guest->partySize() - $guest->seats->count(),
                ])
                ->all()),
        ];
    }
}
