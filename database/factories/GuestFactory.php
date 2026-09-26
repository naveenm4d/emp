<?php

namespace Database\Factories;

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventLink;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Guest\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'source' => GuestSource::Manual,
            'approval_status' => ApprovalStatus::Approved,
            'rsvp_status' => GuestRsvpStatus::NotSent,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1555'.fake()->unique()->numerify('#######'),
        ];
    }

    /** Every guest has a personal link, as when added through GuestService. */
    public function configure(): static
    {
        return $this->afterCreating(fn (Guest $guest) => EventLink::factory()->create([
            'event_id' => $guest->event_id,
            'guest_id' => $guest->id,
        ]));
    }

    public function status(ApprovalStatus $status): static
    {
        return $this->state(['approval_status' => $status]);
    }

    public function withoutPhone(): static
    {
        return $this->state(['phone' => null]);
    }
}
