<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use Carbon\CarbonImmutable;

/**
 * Business subscriptions, like streaming services: renew on their own with a
 * saved card; "cancel" turns auto-renew off and the plan runs to the end of
 * the period; resume before then. The client's plan follows the period.
 */
interface SubscriptionServiceInterface
{
    public function current(Client $client): ?Subscription;

    /** A subscription payment went through: starts (or restarts) the subscription and the Business plan. */
    public function activate(Payment $payment): Subscription;

    /** A renewal went through: the next period starts where this one ends. */
    public function renewed(Payment $payment): Subscription;

    /** The renewal for the period ending at $periodEnd failed: the subscription ends with that period. */
    public function renewalFailed(Subscription $subscription, CarbonImmutable $periodEnd): void;

    /** Turns auto-renew off; the plan stays until the end of the period. */
    public function cancel(Subscription $subscription): Subscription;

    /** Turns auto-renew back on (needs a card that can be charged). */
    public function resume(Subscription $subscription): Subscription;

    /** Monthly ↔ yearly, from the next renewal. */
    public function switchInterval(Subscription $subscription, BillingInterval $interval): Subscription;

    /** The card a renewal is charged to: the subscription's, or else the default card; null when none can be charged. */
    public function renewalCard(Subscription $subscription): ?PaymentMethod;

    /** The card renewals are charged to. */
    public function useCard(Subscription $subscription, PaymentMethod $card): Subscription;

    /**
     * Downgrade from Business when it ends (Celebration or Starter): auto-renew
     * turns off and the new plan starts at the end of the period.
     */
    public function scheduleDowngrade(Client $client, ClientPlan $plan): void;

    /**
     * Keep Business after all: drops the downgrade and turns auto-renew back on.
     *
     * @return string|null a warning when auto-renew couldn't be turned on (no card)
     */
    public function keepPlan(Client $client): ?string;

    /** Staff changed the plan by hand: follow the new end date, or end the subscription. */
    public function followPlanChange(Client $client): void;

    /** Ends canceled subscriptions whose period is over; returns how many. */
    public function endFinished(): int;
}
