<?php

namespace Database\Factories;

use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Models\SeatAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeatAssignment>
 */
class SeatAssignmentFactory extends Factory
{
    protected $model = SeatAssignment::class;

    public function definition(): array
    {
        return [
            'table_id' => EventTable::factory(),
            'event_id' => fn (array $attributes) => EventTable::query()->findOrFail((string) $attributes['table_id'])->event_id,
            'guest_id' => fn (array $attributes) => Guest::factory()->create(['event_id' => $attributes['event_id']])->id,
            'party_member' => 0,
            'seat_number' => 1,
        ];
    }
}
