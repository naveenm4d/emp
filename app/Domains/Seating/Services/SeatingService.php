<?php

namespace App\Domains\Seating\Services;

use App\Core\Services\BaseService;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Exceptions\EventNotEditableException;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\Contracts\EventTableRepositoryInterface;
use App\Domains\Seating\Contracts\SeatAssignmentRepositoryInterface;
use App\Domains\Seating\Contracts\SeatingServiceInterface;
use App\Domains\Seating\Contracts\VenueElementRepositoryInterface;
use App\Domains\Seating\DTOs\FloorPlanData;
use App\Domains\Seating\DTOs\TableData;
use App\Domains\Seating\DTOs\VenueElementData;
use App\Domains\Seating\Enums\SeatMode;
use App\Domains\Seating\Exceptions\GuestNotSeatableException;
use App\Domains\Seating\Exceptions\GuestNotSeatedException;
use App\Domains\Seating\Exceptions\PartyMemberNotFoundException;
use App\Domains\Seating\Exceptions\SeatsOccupiedException;
use App\Domains\Seating\Exceptions\SeatTakenException;
use App\Domains\Seating\Exceptions\TableFullException;
use App\Domains\Seating\Exceptions\TableNameTakenException;
use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Models\SeatAssignment;
use App\Domains\Seating\Models\VenueElement;
use Illuminate\Database\UniqueConstraintViolationException;

class SeatingService extends BaseService implements SeatingServiceInterface
{
    public function __construct(
        private readonly EventTableRepositoryInterface $tables,
        private readonly SeatAssignmentRepositoryInterface $seats,
        private readonly EventQueryServiceInterface $events,
        private readonly GuestQueryServiceInterface $guests,
        private readonly VenueElementRepositoryInterface $elements,
    ) {}

    public function createTable(Event $event, TableData $data): EventTable
    {
        $this->ensureEditable($event);

        return $this->locked($event->id, function () use ($event, $data) {
            $this->ensureUniqueName($event->id, $data->name);

            /** @var EventTable */
            return $this->tables->create([
                'event_id' => $event->id,
                'name' => $data->name,
                'seat_count' => $data->seat_count,
                'shape' => $data->shape,
                'sort_order' => $this->tables->nextSortOrder($event->id),
            ]);
        });
    }

    public function updateTable(EventTable $table, TableData $data): EventTable
    {
        $this->ensureEditable($table->event);

        return $this->locked($table->event_id, function () use ($table, $data) {
            $this->ensureUniqueName($table->event_id, $data->name, $table->id);

            $taken = $this->seats->takenSeats($table->id);

            if ($taken !== [] && max($taken) > $data->seat_count) {
                throw new SeatsOccupiedException;
            }

            /** @var EventTable */
            return $this->tables->update($table, [
                'name' => $data->name,
                'seat_count' => $data->seat_count,
                'shape' => $data->shape,
            ]);
        });
    }

    public function deleteTable(EventTable $table): void
    {
        $this->ensureEditable($table->event);

        $this->tables->delete($table);
    }

    public function saveFloorPlan(Event $event, FloorPlanData $data): void
    {
        $this->ensureEditable($event);

        $this->locked($event->id, function () use ($event, $data) {
            $this->tables->move($event->id, $data->tables);
            $this->elements->place($event->id, $data->elements);
        });
    }

    public function createVenueElement(Event $event, VenueElementData $data): VenueElement
    {
        $this->ensureEditable($event);

        /** @var VenueElement */
        return $this->elements->create(['event_id' => $event->id, ...$this->elementAttributes($data)]);
    }

    public function updateVenueElement(VenueElement $element, VenueElementData $data): VenueElement
    {
        $this->ensureEditable($element->event);

        /** @var VenueElement */
        return $this->elements->update($element, $this->elementAttributes($data));
    }

    public function deleteVenueElement(VenueElement $element): void
    {
        $this->ensureEditable($element->event);

        $this->elements->delete($element);
    }

    public function assign(Guest $guest, EventTable $table, int $seatNumber, SeatMode $mode = SeatMode::Party): int
    {
        $this->ensureEditable($table->event);
        $this->ensureSeatable($guest, $table->event_id);

        return $this->locked($table->event_id, function () use ($guest, $table, $seatNumber, $mode) {
            if ($mode === SeatMode::Rest) {
                return $this->placeMembers($guest, $table, $seatNumber, $this->missingMembers($guest), whole: false);
            }

            $this->seats->releaseGuest($guest->id);

            return $this->placeMembers($guest, $table, $seatNumber, range(0, $guest->partySize() - 1), whole: $mode !== SeatMode::Split);
        });
    }

    public function assignMember(Guest $guest, int $partyMember, EventTable $table, int $seatNumber): void
    {
        $this->ensureEditable($table->event);
        $this->ensureSeatable($guest, $table->event_id);
        $this->ensureInParty($guest, $partyMember);

        $this->locked($table->event_id, function () use ($guest, $partyMember, $table, $seatNumber) {
            $this->seats->releaseMember($guest->id, $partyMember);
            $this->placeMembers($guest, $table, $seatNumber, [$partyMember], whole: true);
        });
    }

    public function unassign(Guest $guest): void
    {
        $this->ensureEditable($guest->event);

        $this->seats->releaseGuest($guest->id);
    }

    public function unassignMember(Guest $guest, int $partyMember): void
    {
        $this->ensureEditable($guest->event);
        $this->ensureInParty($guest, $partyMember);

        $this->seats->releaseMember($guest->id, $partyMember);
    }

    public function swap(Guest $guest, Guest $other): void
    {
        $this->ensureEditable($guest->event);
        $this->ensureSameEvent($guest, $other);

        $this->locked($guest->event_id, function () use ($guest, $other) {
            $first = $this->leadSeat($guest);
            $second = $this->leadSeat($other);

            $this->seats->releaseGuest($guest->id);
            $this->seats->releaseGuest($other->id);

            $this->place($other, $first->table, $first->seat_number);
            $this->place($guest, $second->table, $second->seat_number);
        });
    }

    public function replace(Guest $seated, Guest $other): void
    {
        $this->ensureEditable($seated->event);
        $this->ensureSameEvent($seated, $other);
        $this->ensureSeatable($other, $seated->event_id);

        $this->locked($seated->event_id, function () use ($seated, $other) {
            $lead = $this->leadSeat($seated);

            $this->seats->releaseGuest($seated->id);
            $this->seats->releaseGuest($other->id);

            $this->place($other, $lead->table, $lead->seat_number);
        });
    }

    public function autoSeat(Event $event): array
    {
        $this->ensureEditable($event);

        return $this->locked($event->id, function () use ($event) {
            $waiting = $this->guests->approvedWithSeats($event)
                ->filter(fn (Guest $guest) => $guest->rsvp_status === GuestRsvpStatus::Confirmed && $guest->seats->isEmpty())
                ->sortByDesc(fn (Guest $guest) => $guest->partySize());

            $tables = $this->tables->forEvent($event->id);
            $result = ['seated' => 0, 'unplaced' => 0];

            foreach ($waiting as $guest) {
                $table = $tables->first(fn (EventTable $table) => count($this->freeSeats($table)) >= $guest->partySize());

                if ($table === null) {
                    $result['unplaced']++;

                    continue;
                }

                $this->place($guest, $table, $this->freeSeats($table)[0]);
                $result['seated']++;
            }

            return $result;
        });
    }

    public function syncParty(Guest $guest): void
    {
        $this->locked($guest->event_id, function () use ($guest) {
            $seated = $this->seats->forGuest($guest->id);
            $size = $guest->partySize();

            if ($seated->isEmpty() || $seated->count() === $size) {
                return;
            }

            if ($seated->count() > $size) {
                $this->seats->releaseMembersFrom($guest->id, $size);

                return;
            }

            // A bigger party: take free seats at the same table while there are any.
            /** @var SeatAssignment $lead */
            $lead = $seated->first();
            $free = $this->freeSeats($lead->table);

            foreach (range($seated->count(), $size - 1) as $index => $member) {
                if (! isset($free[$index])) {
                    break;
                }

                $this->createSeat($guest, $lead->table, $member, $free[$index]);
            }
        });
    }

    /** Seats the whole party from the given seat on. All or nothing. */
    private function place(Guest $guest, EventTable $table, int $seatNumber): void
    {
        $this->placeMembers($guest, $table, $seatNumber, range(0, $guest->partySize() - 1), whole: true);
    }

    /**
     * Seats these party members (in order) from the given seat on, going
     * round the table through the free seats. With $whole, all of them or
     * none; otherwise as many as fit.
     *
     * @param  list<int>  $members
     * @return int how many were seated
     */
    private function placeMembers(Guest $guest, EventTable $table, int $seatNumber, array $members, bool $whole): int
    {
        $free = $this->freeSeats($table);
        $start = array_search($seatNumber, $free, true);

        if ($start === false) {
            throw new SeatTakenException;
        }

        if ($whole && count($free) < count($members)) {
            throw new TableFullException("{$table->name} has ".count($free)." free seats; {$guest->name}'s party needs ".count($members).'.');
        }

        $order = [...array_slice($free, $start), ...array_slice($free, 0, $start)];
        $seated = array_slice($members, 0, count($order));

        foreach ($seated as $index => $member) {
            $this->createSeat($guest, $table, $member, $order[$index]);
        }

        return count($seated);
    }

    /**
     * The party members without a seat, in order.
     *
     * @return list<int>
     */
    private function missingMembers(Guest $guest): array
    {
        $seated = $this->seats->forGuest($guest->id)->pluck('party_member')->all();

        return array_values(array_diff(range(0, $guest->partySize() - 1), $seated));
    }

    private function ensureInParty(Guest $guest, int $partyMember): void
    {
        if ($partyMember < 0 || $partyMember >= $guest->partySize()) {
            throw new PartyMemberNotFoundException;
        }
    }

    private function createSeat(Guest $guest, EventTable $table, int $member, int $seatNumber): void
    {
        try {
            $this->seats->create([
                'event_id' => $table->event_id,
                'table_id' => $table->id,
                'guest_id' => $guest->id,
                'party_member' => $member,
                'seat_number' => $seatNumber,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Lost a race for the seat.
            throw new SeatTakenException(previous: $e);
        }
    }

    /** @return list<int> */
    private function freeSeats(EventTable $table): array
    {
        return array_values(array_diff(range(1, $table->seat_count), $this->seats->takenSeats($table->id)));
    }

    /** Where the guest themself sits (party member 0, or the first seat of their party). */
    private function leadSeat(Guest $guest): SeatAssignment
    {
        return $this->seats->forGuest($guest->id)->first()
            ?? throw new GuestNotSeatedException("{$guest->name} doesn't have a seat yet.");
    }

    private function ensureSeatable(Guest $guest, string $eventId): void
    {
        if ($guest->event_id !== $eventId
            || $guest->approval_status !== ApprovalStatus::Approved
            || $guest->rsvp_status === GuestRsvpStatus::Declined) {
            throw new GuestNotSeatableException;
        }
    }

    private function ensureSameEvent(Guest $guest, Guest $other): void
    {
        if ($guest->event_id !== $other->event_id || $guest->is($other)) {
            throw new GuestNotSeatableException('Pick another guest of this event.');
        }
    }

    /** @return array<string, mixed> */
    private function elementAttributes(VenueElementData $data): array
    {
        return [
            'kind' => $data->kind,
            'label' => $data->label,
            'pos_x' => $data->x,
            'pos_y' => $data->y,
            'width' => $data->width,
            'height' => $data->height,
        ];
    }

    private function ensureUniqueName(string $eventId, string $name, ?string $exceptId = null): void
    {
        if ($this->tables->nameExists($eventId, $name, $exceptId)) {
            throw new TableNameTakenException;
        }
    }

    private function ensureEditable(Event $event): void
    {
        if (! $event->state->isEditable()) {
            throw new EventNotEditableException;
        }
    }

    /**
     * Runs the change holding the event's row lock, so two people arranging
     * seats at once can't double-book.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(string $eventId, callable $callback): mixed
    {
        return $this->transaction(function () use ($eventId, $callback) {
            $this->events->findAndLock($eventId);

            return $callback();
        });
    }
}
