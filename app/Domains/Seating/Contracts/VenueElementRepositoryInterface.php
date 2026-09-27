<?php

namespace App\Domains\Seating\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Seating\Models\VenueElement;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<VenueElement>
 */
interface VenueElementRepositoryInterface extends RepositoryInterface
{
    /** @return Collection<int, VenueElement> oldest first, so later ones draw on top */
    public function forEvent(string $eventId): Collection;

    /**
     * Moves / resizes the event's elements; ids of other events are ignored.
     *
     * @param  array<string, array{x: int, y: int, width: int, height: int}>  $placements  by element id
     */
    public function place(string $eventId, array $placements): void;
}
