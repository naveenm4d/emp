<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Exceptions\PaymentGatewayException;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Staff\Models\StaffMember;

/**
 * PayHere's answers. A checkout becomes a payment only once PayHere processed
 * it (paid or declined); while pending it stays a checkout, and a canceled one
 * is dropped. A payment's status only moves forward and what was bought is
 * applied once.
 */
interface PaymentServiceInterface
{
    /**
     * Handles a call to the notify URL. Only signed notifications for a known
     * order with exactly the expected amount change anything; every call is logged.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleNotification(array $payload): void;

    /**
     * PayHere's verdict on a checkout: paid or declined records the payment (a
     * success applies what was bought); pending keeps the checkout; canceled drops it.
     *
     * @return Payment|null the payment, once PayHere processed the checkout
     */
    public function record(Checkout $checkout, PaymentStatus $status, ?string $payherePaymentId = null, ?string $message = null): ?Payment;

    /** A later verdict on a recorded payment (paid after a decline, refunded, charged back). */
    public function settle(Payment $payment, PaymentStatus $status, ?string $payherePaymentId = null, ?string $message = null): Payment;

    /**
     * Charges a saved card for a checkout and records PayHere's answer.
     *
     * @return Payment|Checkout the payment, or the checkout while PayHere still processes it
     *
     * @throws PaymentGatewayException when PayHere can't be reached (the checkout stays)
     */
    public function chargeSavedCard(Checkout $checkout, PaymentMethod $card): Payment|Checkout;

    /** Applies what a successful payment bought, if that hasn't happened yet (retries after an error). */
    public function fulfil(Payment $payment): Payment;

    /** Staff refund a payment in full through PayHere; logged with the note. What was bought stays. */
    public function refund(Payment $payment, StaffMember $by, string $note): Payment;
}
