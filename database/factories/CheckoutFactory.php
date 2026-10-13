<?php

namespace Database\Factories;

use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Client\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A one-off checkout for one Celebration event, sent to PayHere and waiting for its answer.
 *
 * @extends Factory<Checkout>
 */
class CheckoutFactory extends Factory
{
    protected $model = Checkout::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'order_id' => Checkout::newOrderId(),
            'purpose' => PaymentPurpose::EventCredits,
            'channel' => PaymentChannel::Checkout,
            'description' => 'Celebration event',
            'details' => ['quantity' => 1],
            'amount' => 499000,
            'currency' => 'LKR',
        ];
    }
}
