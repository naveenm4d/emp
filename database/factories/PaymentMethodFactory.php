<?php

namespace Database\Factories;

use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A saved Visa card that can be charged.
 *
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'customer_token' => 'tok-'.Str::random(32),
            'card_holder_name' => fake()->name(),
            'brand' => 'VISA',
            'last4' => (string) fake()->numberBetween(1000, 9999),
            'expiry_month' => 12,
            'expiry_year' => (int) now()->addYears(3)->format('Y'),
            'is_default' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expiry_month' => 1, 'expiry_year' => (int) now()->subYear()->format('Y')]);
    }
}
