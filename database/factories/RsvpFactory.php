<?php

namespace Database\Factories;

use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rsvp>
 */
class RsvpFactory extends Factory
{
    protected $model = Rsvp::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'event_id' => fn (array $attributes) => Guest::query()->whereKey($attributes['guest_id'])->value('event_id'),
            'status' => RsvpStatus::Pending,
            'token' => bin2hex(random_bytes(32)),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => RsvpStatus::Sent,
            'sent_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function expiredYesterday(): static
    {
        return $this->state(fn () => [
            'status' => RsvpStatus::Sent,
            'sent_at' => now()->subDays(8),
            'expires_at' => now()->subDay(),
        ]);
    }
}
