<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\DTOs\ChargeResult;
use App\Domains\Billing\DTOs\GatewayRedirect;
use App\Domains\Billing\DTOs\PayerData;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;

/**
 * PayHere: its hosted pages (checkout, preapproval), the notifications it
 * sends, and the merchant API (charging, retrieval, refunds). Secrets never
 * leave this class. Without credentials a mock gateway stands in.
 */
interface PaymentGatewayInterface
{
    /** The mock: payments complete on a local page, nothing reaches PayHere. */
    public function isMock(): bool;

    /** The signed form that sends the payer to PayHere's checkout or preapproval page. */
    public function redirect(Checkout $checkout, PayerData $payer): GatewayRedirect;

    /**
     * The notification came from PayHere for this merchant (md5sig).
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotification(array $payload): bool;

    /** Charges a saved card now (Charging API). Throws PaymentGatewayException when PayHere can't be reached. */
    public function charge(Checkout $checkout, PaymentMethod $card): ChargeResult;

    /**
     * Asks PayHere whether it received the payment (Retrieval API).
     *
     * @return bool|null null when it can't tell (no API key, or PayHere didn't answer)
     */
    public function confirm(Checkout $checkout): ?bool;

    /** Refunds a payment in full; returns PayHere's refund reference. */
    public function refund(Payment $payment, string $reason): string;
}
