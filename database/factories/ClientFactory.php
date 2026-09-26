<?php

namespace Database\Factories;

use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Business without an end date: no plan limits get in the way unless a test picks a plan.
            'plan' => ClientPlan::Business,
            'plan_expires_at' => null,
            'event_credits' => 0,
        ];
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
