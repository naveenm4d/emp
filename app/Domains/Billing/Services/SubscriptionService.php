<?php

namespace App\Domains\Billing\Services;

use App\Core\Services\BaseService;
use App\Domains\Billing\Contracts\PaymentMethodRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentRepositoryInterface;
use App\Domains\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Exceptions\PaymentMethodUnavailableException;
use App\Domains\Billing\Exceptions\SubscriptionStateException;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\SubscriptionEnded;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\DTOs\ClientPlanData;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use Carbon\CarbonImmutable;

class SubscriptionService extends BaseService implements SubscriptionServiceInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentMethodRepositoryInterface $cards,
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function current(Client $client): ?Subscription
    {
        return $this->subscriptions->forClient($client->id);
    }

    public function activate(Payment $payment): Subscription
    {
        return $this->transaction(function () use ($payment) {
            $client = $payment->client;
            $interval = BillingInterval::from((string) ($payment->details['interval'] ?? BillingInterval::Monthly->value));

            // Paying while a Business plan still runs extends it rather than overlapping.
            $start = CarbonImmutable::now();
            if ($client->plan === ClientPlan::Business && $client->plan_expires_at?->isFuture()) {
                $start = CarbonImmutable::parse($client->plan_expires_at);
            }
            $end = $interval->periodEnd($start);

            $attributes = [
                'client_id' => $client->id,
                'plan' => ClientPlan::Business,
                'interval' => $interval,
                'next_interval' => null,
                'status' => SubscriptionStatus::Active,
                'auto_renew' => true,
                'payment_method_id' => $payment->payment_method_id,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'canceled_at' => null,
                'renewal_reminded_at' => null,
                'ended_reason' => null,
            ];

            $existing = $this->subscriptions->forClient($client->id);

            /** @var Subscription $subscription */
            $subscription = $existing
                ? $this->subscriptions->update($this->subscriptions->findAndLock($existing->id), $attributes)
                : $this->subscriptions->create($attributes);

            $this->payments->update($payment, ['subscription_id' => $subscription->id]);
            $this->applyPlan($client, $end, "Payment {$payment->order_id}");

            return $subscription;
        });
    }

    public function renewed(Payment $payment): Subscription
    {
        return $this->transaction(function () use ($payment) {
            $subscription = $this->subscriptions->findAndLock((string) $payment->subscription_id);

            // Already moved on (this period was renewed before).
            if ($payment->renewal_period_end === null || ! $payment->renewal_period_end->equalTo($subscription->current_period_end)) {
                return $subscription;
            }

            $interval = $subscription->renewalInterval();
            $start = CarbonImmutable::parse($subscription->current_period_end);
            $end = $interval->periodEnd($start);

            /** @var Subscription $subscription */
            $subscription = $this->subscriptions->update($subscription, [
                'interval' => $interval,
                'next_interval' => null,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'renewal_reminded_at' => null,
                'payment_method_id' => $payment->payment_method_id ?? $subscription->payment_method_id,
            ]);

            $this->applyPlan($subscription->client, $end, "Payment {$payment->order_id} (renewal)");

            return $subscription;
        });
    }

    public function renewalFailed(Subscription $subscription, CarbonImmutable $periodEnd): void
    {
        $subscription = $this->subscriptions->findAndLock($subscription->id);

        if ($subscription->status !== SubscriptionStatus::Active || ! $periodEnd->equalTo($subscription->current_period_end)) {
            return;
        }

        // The plan keeps its end date (plan_expires_at) and lapses then: no retries.
        $this->subscriptions->update($subscription, [
            'status' => SubscriptionStatus::Expired,
            'auto_renew' => false,
            'ended_reason' => 'renewal_failed',
        ]);
    }

    public function cancel(Subscription $subscription): Subscription
    {
        return $this->transaction(function () use ($subscription) {
            $subscription = $this->subscriptions->findAndLock($subscription->id);

            if ($subscription->status !== SubscriptionStatus::Active || ! $subscription->isRunning()) {
                throw new SubscriptionStateException('Auto-renew is already off.');
            }

            /** @var Subscription */
            return $this->subscriptions->update($subscription, [
                'status' => SubscriptionStatus::Canceled,
                'auto_renew' => false,
                'canceled_at' => now(),
            ]);
        });
    }

    public function resume(Subscription $subscription): Subscription
    {
        return $this->transaction(function () use ($subscription) {
            $subscription = $this->subscriptions->findAndLock($subscription->id);

            if ($subscription->status !== SubscriptionStatus::Canceled || ! $subscription->isRunning()) {
                throw new SubscriptionStateException('This subscription has ended. Subscribe again to continue.');
            }

            $card = $this->renewalCard($subscription)
                ?? throw new PaymentMethodUnavailableException('Add a card first: renewals are charged to it.');

            // Renewing again means no downgrade at the end.
            if ($subscription->client->scheduled_plan !== null) {
                $this->plans->scheduleChange($subscription->client, null);
            }

            /** @var Subscription */
            return $this->subscriptions->update($subscription, [
                'status' => SubscriptionStatus::Active,
                'auto_renew' => true,
                'canceled_at' => null,
                'payment_method_id' => $card->id,
            ]);
        });
    }

    public function switchInterval(Subscription $subscription, BillingInterval $interval): Subscription
    {
        if (! $subscription->isRunning()) {
            throw new SubscriptionStateException('This subscription has ended. Subscribe again to continue.');
        }

        /** @var Subscription */
        return $this->subscriptions->update($subscription, [
            'next_interval' => $interval === $subscription->interval ? null : $interval,
            // A yearly renewal gets its reminder again.
            'renewal_reminded_at' => null,
        ]);
    }

    public function useCard(Subscription $subscription, PaymentMethod $card): Subscription
    {
        if ($card->client_id !== $subscription->client_id || ! $card->isChargeable()) {
            throw new PaymentMethodUnavailableException;
        }

        /** @var Subscription */
        return $this->subscriptions->update($subscription, ['payment_method_id' => $card->id]);
    }

    public function scheduleDowngrade(Client $client, ClientPlan $plan): void
    {
        $this->transaction(function () use ($client, $plan) {
            $this->plans->scheduleChange($client, $plan);

            $subscription = $this->subscriptions->forClient($client->id);

            if ($subscription !== null && $subscription->status === SubscriptionStatus::Active && $subscription->isRunning()) {
                $this->cancel($subscription);
            }
        });
    }

    public function keepPlan(Client $client): ?string
    {
        return $this->transaction(function () use ($client) {
            $this->plans->scheduleChange($client, null);

            $subscription = $this->subscriptions->forClient($client->id);

            if ($subscription === null || $subscription->status !== SubscriptionStatus::Canceled || ! $subscription->isRunning()) {
                return null;
            }

            try {
                $this->resume($subscription);
            } catch (PaymentMethodUnavailableException) {
                return "Business stays until {$subscription->current_period_end->format('j M Y')}. Add a card to turn auto-renew back on.";
            }

            return null;
        });
    }

    public function followPlanChange(Client $client): void
    {
        $subscription = $this->subscriptions->forClient($client->id);

        if ($subscription === null || $subscription->status === SubscriptionStatus::Expired) {
            return;
        }

        $client->refresh();

        // Still Business with an end date: renew from the new date.
        if ($client->plan === ClientPlan::Business && $client->plan_expires_at !== null && $client->plan_expires_at->isFuture()) {
            $this->subscriptions->update($subscription, [
                'current_period_end' => $client->plan_expires_at,
                'renewal_reminded_at' => null,
            ]);

            return;
        }

        $this->subscriptions->update($subscription, [
            'status' => SubscriptionStatus::Expired,
            'auto_renew' => false,
            'ended_reason' => 'plan_changed',
        ]);
    }

    public function endFinished(): int
    {
        $ended = 0;

        foreach ($this->subscriptions->endedWithoutRenewal(CarbonImmutable::now()) as $subscription) {
            $this->subscriptions->update($subscription, [
                'status' => SubscriptionStatus::Expired,
                'ended_reason' => 'canceled',
            ]);

            $subscription->client->owner?->notify(new SubscriptionEnded($subscription));
            $ended++;
        }

        return $ended;
    }

    public function renewalCard(Subscription $subscription): ?PaymentMethod
    {
        $card = $subscription->paymentMethod;

        if ($card !== null && $card->isChargeable()) {
            return $card;
        }

        $default = $this->cards->defaultFor($subscription->client_id);

        return $default !== null && $default->isChargeable() ? $default : null;
    }

    private function applyPlan(Client $client, CarbonImmutable $end, string $reference): void
    {
        $this->plans->applyPurchase($client, new ClientPlanData(
            plan: ClientPlan::Business,
            expiresAt: $end,
            eventCredits: $client->event_credits,
            userLimit: $client->user_limit,
        ), $reference);
    }
}
