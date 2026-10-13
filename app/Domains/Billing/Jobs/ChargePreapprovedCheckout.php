<?php

namespace App\Domains\Billing\Jobs;

use App\Domains\Billing\Contracts\CheckoutRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\PaymentMethod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Charges a purchase to the card PayHere just saved for it (preapproval).
 * Runs on the queue so the notification is answered quickly; retried when
 * PayHere can't be reached (the checkout waits meanwhile).
 */
class ChargePreapprovedCheckout implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $checkoutId,
    ) {}

    public function handle(CheckoutRepositoryInterface $checkouts, PaymentServiceInterface $payments): void
    {
        /** @var Checkout|null $checkout */
        $checkout = $checkouts->find($this->checkoutId);
        $card = $checkout?->paymentMethod;

        if ($checkout === null || $checkout->channel !== PaymentChannel::Preapproval || ! $card instanceof PaymentMethod) {
            return;
        }

        $payments->chargeSavedCard($checkout, $card);
    }
}
