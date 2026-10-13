<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard\Concerns;

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use Illuminate\Http\RedirectResponse;

/**
 * After starting a purchase: off to PayHere with the checkout, to the order
 * while PayHere still processes a saved-card charge, or to the recorded payment.
 */
trait RespondsWithPayment
{
    protected function afterPurchase(Payment|Checkout $result): RedirectResponse
    {
        if ($result instanceof Checkout) {
            return $result->channel->redirects() && $result->payment_method_id === null
                ? to_route('client.billing.checkouts.pay', $result)
                : to_route('client.billing.orders.show', $result->order_id)->with('success', 'PayHere is processing the payment.');
        }

        $redirect = to_route('client.billing.payments.show', $result);

        return $result->status === PaymentStatus::Succeeded
            ? $redirect->with('success', 'Payment received. Thank you!')
            : $redirect->with('error', 'The payment didn\'t go through'.($result->status_message ? ": {$result->status_message}" : '.'));
    }
}
