<?php

namespace Database\Factories;

use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Enums\NotificationChannel;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'event_id' => fn (array $attributes) => Guest::query()->whereKey($attributes['guest_id'])->value('event_id'),
            'channel' => NotificationChannel::WhatsApp,
            'status' => NotificationStatus::Pending,
            'recipient' => '+1555'.fake()->numerify('#######'),
            'message' => fake()->sentence(),
        ];
    }

    public function failed(string $error = 'Provider error'): static
    {
        return $this->state(['status' => NotificationStatus::Failed, 'error' => $error, 'attempts' => 3]);
    }
}
