<?php

namespace Database\Factories;

use App\Domains\Event\Models\Event;
use App\Domains\Seating\Enums\TableShape;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventTable>
 */
class EventTableFactory extends Factory
{
    protected $model = EventTable::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Table '.fake()->unique()->numberBetween(1, 999),
            'seat_count' => 8,
            'shape' => TableShape::Round,
            'sort_order' => 0,
        ];
    }

    public function seats(int $count): static
    {
        return $this->state(['seat_count' => $count]);
    }
}
