<?php

namespace App\Domains\Seating\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Seating\Contracts\EventTableRepositoryInterface;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<EventTable>
 */
class EventTableRepository extends BaseRepository implements EventTableRepositoryInterface
{
    protected function model(): string
    {
        return EventTable::class;
    }

    public function forEvent(string $eventId): Collection
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->with('seats.guest')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    public function nameExists(string $eventId, string $name, ?string $exceptId = null): bool
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId, fn (Builder $q, string $id) => $q->whereKeyNot($id))
            ->exists();
    }

    public function nextSortOrder(string $eventId): int
    {
        return (int) $this->query()->where('event_id', $eventId)->max('sort_order') + 1;
    }
}
