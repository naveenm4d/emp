<?php

namespace Database\Factories;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Guest\Enums\RsvpStatus;
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
            'rsvp_status' => RsvpStatus::NotSent,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1555'.fake()->unique()->numerify('#######'),
        ];
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
