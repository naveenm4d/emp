<?php

namespace Database\Factories;

use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // Business without an end date: no plan limits get in the way unless a test picks a plan.
            'plan' => ClientPlan::Business,
            'plan_expires_at' => null,
            'event_credits' => 0,
        ];
    }

    /** Every account has an owner who signs in with the account's email and the password "password". */
    public function configure(): static
    {
        return $this->afterCreating(function (Client $client) {
            ClientUser::factory()->owner()->for($client)->create([
                'name' => $client->name,
                'email' => $client->email,
            ]);
        });
    }

    public function starter(): static
    {
        return $this->state(['plan' => ClientPlan::Starter, 'plan_expires_at' => null, 'event_credits' => 0]);
    }

    public function celebration(int $credits = 1): static
    {
        return $this->state(['plan' => ClientPlan::Celebration, 'plan_expires_at' => null, 'event_credits' => $credits]);
    }

    public function business(?CarbonInterface $expiresAt = null): static
    {
        return $this->state(['plan' => ClientPlan::Business, 'plan_expires_at' => $expiresAt]);
    }

    public function enterprise(?CarbonInterface $expiresAt = null): static
    {
        return $this->state(['plan' => ClientPlan::Enterprise, 'plan_expires_at' => $expiresAt]);
    }
}
