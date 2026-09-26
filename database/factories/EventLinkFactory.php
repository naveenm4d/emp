<?php

namespace Database\Factories;

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventLink>
 */
class EventLinkFactory extends Factory
{
    protected $model = EventLink::class;

    public function definition(): array
    {
        return [
            'code' => EventLink::generateCode(),
            'event_id' => Event::factory(),
            'guest_id' => null,
        ];
    }
}
