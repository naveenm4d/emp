<?php

namespace App\Domains\Seating\Models;

use App\Domains\Event\Models\Event;
use App\Domains\Seating\Enums\TableShape;
use Carbon\CarbonImmutable;
use Database\Factories\EventTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table at an event, with a fixed number of seats numbered 1..seat_count.
 *
 * @property string $id
 * @property string $event_id
 * @property string $name e.g. "Table 1" or "A"; unique per event (case-insensitive)
 * @property int $seat_count
 * @property TableShape $shape how the table is drawn
 * @property int $sort_order
 * @property-read Event $event
 * @property-read Collection<int, SeatAssignment> $seats
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(EventTableFactory::class)]
#[Fillable(['event_id', 'name', 'seat_count', 'shape', 'sort_order'])]
class EventTable extends Model
{
    /** @use HasFactory<EventTableFactory> */
    use HasFactory, HasUuids;

    public const int MAX_SEATS = 50;

    protected $attributes = [
        'shape' => 'round',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'shape' => TableShape::class,
            'seat_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Seat numbers nobody sits on, in order.
     *
     * @return list<int>
     */
    public function freeSeatNumbers(): array
    {
        $taken = $this->seats->pluck('seat_number')->all();

        return array_values(array_diff(range(1, $this->seat_count), $taken));
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<SeatAssignment, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(SeatAssignment::class, 'table_id')->orderBy('seat_number');
    }
}
