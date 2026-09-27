<?php

namespace App\Domains\Seating\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<EventTable>
 */
interface EventTableRepositoryInterface extends RepositoryInterface
{
    /** @return Collection<int, EventTable> in order, with their seats and the seated guests */
    public function forEvent(string $eventId): Collection;

    public function nameExists(string $eventId, string $name, ?string $exceptId = null): bool;

    public function nextSortOrder(string $eventId): int;

    /**
     * Moves the event's tables on the floor plan; ids of other events are ignored.
     *
     * @param  array<string, array{x: int, y: int}>  $positions  by table id
     */
    public function move(string $eventId, array $positions): void;
}
