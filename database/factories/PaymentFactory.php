<?php

namespace Database\Factories;

use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Client\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A paid one-off checkout for one Celebration event.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'order_id' => Checkout::newOrderId(),
            'purpose' => PaymentPurpose::EventCredits,
            'channel' => PaymentChannel::Checkout,
            'status' => PaymentStatus::Succeeded,
            'description' => 'Celebration event',
            'details' => ['quantity' => 1],
            'amount' => 499000,
            'currency' => 'LKR',
            'payhere_payment_id' => (string) fake()->numberBetween(320000000000, 329999999999),
            'paid_at' => now(),
            'fulfilled_at' => now(),
        ];
    }

    public function declined(): static
    {
        return $this->state(['status' => PaymentStatus::Failed, 'paid_at' => null, 'fulfilled_at' => null, 'status_message' => 'Declined']);
    }
}
