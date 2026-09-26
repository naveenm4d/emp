<?php

namespace Database\Factories;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventLink;
use App\Domains\Template\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = fake()->randomElement(['Summer Gala', 'Tech Meetup', 'Product Launch', 'Charity Dinner', 'Wedding Reception', 'Alumni Night']).' '.fake()->year();

        return [
            'client_id' => Client::factory(),
            'template_version_id' => TemplateVersion::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'state' => EventState::Draft,
            'max_capacity' => 0,
            'registration_type' => RegistrationType::GuestListOnly,
            'registration_open' => false,
            'event_type' => fake()->randomElement(EventType::cases()),
            'location_name' => fake()->company(),
            'location_address' => fake()->address(),
            'event_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'start_time' => '18:00',
            'end_time' => '22:00',
        ];
    }

    /** Every event has a public URL link, as when created through EventService. */
    public function configure(): static
    {
        return $this->afterCreating(fn (Event $event) => EventLink::factory()->create(['event_id' => $event->id]));
    }

    public function published(): static
    {
        return $this->state(['state' => EventState::Published]);
    }

    public function cancelled(): static
    {
        return $this->state(['state' => EventState::Cancelled, 'registration_open' => false]);
    }

    public function openForRegistration(): static
    {
        return $this->published()->state(['registration_type' => RegistrationType::Open, 'registration_open' => true]);
    }

    public function capacity(int $capacity): static
    {
        return $this->state(['max_capacity' => $capacity]);
    }

    public function requiresApproval(): static
    {
        return $this->state(['registration_type' => RegistrationType::ApprovalRequired]);
    }
}
