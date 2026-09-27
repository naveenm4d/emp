<?php

namespace App\Domains\Seating\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Seating\Contracts\VenueElementRepositoryInterface;
use App\Domains\Seating\Models\VenueElement;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<VenueElement>
 */
class VenueElementRepository extends BaseRepository implements VenueElementRepositoryInterface
{
    protected function model(): string
    {
        return VenueElement::class;
    }

    public function forEvent(string $eventId): Collection
    {
        return $this->query()->where('event_id', $eventId)->orderBy('created_at')->get();
    }

    public function place(string $eventId, array $placements): void
    {
        foreach ($placements as $id => $placement) {
            $this->query()->where('event_id', $eventId)->whereKey($id)->update([
                'pos_x' => $placement['x'],
                'pos_y' => $placement['y'],
                'width' => $placement['width'],
                'height' => $placement['height'],
            ]);
        }
    }
}
