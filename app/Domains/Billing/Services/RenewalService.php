<?php

namespace App\Domains\Billing\Services;

use App\Core\Services\BaseService;
use App\Domains\Billing\Contracts\CheckoutRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Contracts\PaymentRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use App\Domains\Billing\Contracts\PriceServiceInterface;
use App\Domains\Billing\Contracts\RenewalServiceInterface;
use App\Domains\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Exceptions\PaymentGatewayException;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\RenewalFailed;
use App\Domains\Billing\Notifications\RenewalUpcoming;
use App\Domains\Billing\Support\Money;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenewalService extends BaseService implements RenewalServiceInterface
{
    /** Renewals are charged up to this long before the period ends. */
    private const int CHARGE_HOURS_BEFORE = 24;

    /** Yearly subscribers hear about the renewal this many days ahead. */
    private const int REMIND_DAYS_BEFORE = 3;

    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly PaymentRepositoryInterface $payments,
        private readonly CheckoutRepositoryInterface $checkouts,
        private readonly PaymentServiceInterface $paymentService,
        private readonly PriceServiceInterface $prices,
        private readonly PaymentGatewayInterface $gateway,
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function run(): array
    {
        $result = ['renewed' => 0, 'failed' => 0, 'ended' => 0, 'downgraded' => 0, 'reminded' => 0, 'applied' => 0, 'abandoned' => 0];

        foreach ($this->subscriptionRepository->dueForRenewal(CarbonImmutable::now()->addHours(self::CHARGE_HOURS_BEFORE)) as $subscription) {
            try {
                $status = $this->renew($subscription);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            match ($status) {
                PaymentStatus::Succeeded => $result['renewed']++,
                PaymentStatus::Failed => $result['failed']++,
                default => null,
            };
        }

        $result['ended'] = $this->subscriptions->endFinished();
        $result['downgraded'] = $this->plans->applyScheduledChanges();
        $result['reminded'] = $this->remind();
        $result['applied'] = $this->applyUnfulfilled();
        $result['abandoned'] = $this->checkouts->pruneAbandoned(CarbonImmutable::now()->subDay());

        return $result;
    }

    /**
     * One charge per period. A renewal still waiting from an earlier run is
     * checked with PayHere, then tried again.
     *
     * @return PaymentStatus|null the outcome, or null while PayHere hasn't processed it
     */
    private function renew(Subscription $subscription): ?PaymentStatus
    {
        $period = CarbonImmutable::parse($subscription->current_period_end);

        if ($this->payments->renewalFor($subscription->id, $period) !== null) {
            return null; // PayHere already processed this period's renewal
        }

        $card = $this->subscriptions->renewalCard($subscription);
        $checkout = $this->checkouts->renewalFor($subscription->id, $period);

        if ($checkout !== null && $this->gateway->confirm($checkout) === true) {
            return $this->paymentService->record($checkout, PaymentStatus::Succeeded, null, 'Confirmed with PayHere.')?->status;
        }

        // Nothing to charge: PayHere never sees it, so no payment is recorded; the plan ends with the period.
        if ($card === null) {
            if ($checkout !== null) {
                $this->checkouts->delete($checkout);
            }

            $this->subscriptions->renewalFailed($subscription, $period);
            $subscription->client->owner?->notify(new RenewalFailed($subscription, 'no card on file'));

            return PaymentStatus::Failed;
        }

        if ($checkout === null) {
            $interval = $subscription->renewalInterval();
            $price = $this->prices->priceOf($interval->product(), onSale: false);

            try {
                /** @var Checkout $checkout */
                $checkout = $this->checkouts->create([
                    'client_id' => $subscription->client_id,
                    'order_id' => Checkout::newOrderId(),
                    'purpose' => PaymentPurpose::Renewal,
                    'channel' => PaymentChannel::SavedCard,
                    'description' => config('app.name')." Business plan ({$interval->label()} renewal)",
                    'subscription_id' => $subscription->id,
                    'renewal_period_end' => $period,
                    'details' => ['interval' => $interval->value],
                    'amount' => $price->amount,
                    'currency' => $price->currency,
                    'payment_method_id' => $card->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                return null; // another run got here first
            }
        }

        try {
            $outcome = $this->paymentService->chargeSavedCard($checkout, $card);
        } catch (PaymentGatewayException $e) {
            // Nothing processed: the next run asks PayHere and tries again.
            Log::warning('Renewal charge could not reach PayHere.', ['order_id' => $checkout->order_id, 'error' => $e->getMessage()]);

            return null;
        }

        return $outcome instanceof Payment ? $outcome->status : null;
    }

    private function remind(): int
    {
        $reminded = 0;

        foreach ($this->subscriptionRepository->dueForRenewalReminder(CarbonImmutable::now()->addDays(self::REMIND_DAYS_BEFORE)) as $subscription) {
            $price = $this->prices->priceOf($subscription->renewalInterval()->product(), onSale: false);

            $subscription->client->owner?->notify(new RenewalUpcoming($subscription, Money::format($price->amount, $price->currency)));
            $this->subscriptionRepository->update($subscription, ['renewal_reminded_at' => now()]);
            $reminded++;
        }

        return $reminded;
    }

    private function applyUnfulfilled(): int
    {
        $applied = 0;

        foreach ($this->payments->unfulfilled() as $payment) {
            if ($this->paymentService->fulfil($payment)->fulfilled_at !== null) {
                $applied++;
            }
        }

        return $applied;
    }
}
