<?php

namespace Database\Factories;

use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A monthly Business subscription that renews, half way through its period.
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'plan' => ClientPlan::Business,
            'interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'auto_renew' => true,
            'current_period_start' => now()->subDays(15),
            'current_period_end' => now()->addDays(15),
        ];
    }

    /** Auto-renew off; runs until the end of the period. */
    public function canceled(): static
    {
        return $this->state(['status' => SubscriptionStatus::Canceled, 'auto_renew' => false, 'canceled_at' => now()]);
    }

    /** The period ends in a few hours: due for renewal. */
    public function dueForRenewal(): static
    {
        return $this->state(['current_period_start' => now()->subMonth()->addHours(5), 'current_period_end' => now()->addHours(5)]);
    }
}
