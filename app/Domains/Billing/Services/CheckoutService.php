<?php

namespace App\Domains\Billing\Services;

use App\Core\Services\BaseService;
use App\Domains\Billing\Contracts\CheckoutRepositoryInterface;
use App\Domains\Billing\Contracts\CheckoutServiceInterface;
use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use App\Domains\Billing\Contracts\PriceServiceInterface;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\DTOs\BillingDetailsData;
use App\Domains\Billing\DTOs\GatewayRedirect;
use App\Domains\Billing\DTOs\PayerData;
use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\BillingProduct;
use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Exceptions\BillingDetailsMissingException;
use App\Domains\Billing\Exceptions\PaymentMethodUnavailableException;
use App\Domains\Billing\Exceptions\PurchaseNotAllowedException;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Models\Template;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CheckoutService extends BaseService implements CheckoutServiceInterface
{
    /** PayHere's smallest preapproval amount (Rs. 10.00), used when a card is only saved. */
    private const int CARD_SETUP_AMOUNT = 1000;

    public function __construct(
        private readonly PriceServiceInterface $prices,
        private readonly CheckoutRepositoryInterface $checkouts,
        private readonly PaymentServiceInterface $paymentService,
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly PaymentGatewayInterface $gateway,
        private readonly ClientRepositoryInterface $clients,
        private readonly TemplateQueryServiceInterface $templates,
    ) {}

    public function subscribe(ClientUser $by, BillingInterval $interval, ?PaymentMethod $card): Payment|Checkout
    {
        return $this->exclusively($by->client, function () use ($by, $interval, $card) {
            $client = $by->client->refresh();
            $this->ensureCanSubscribe($client);
            $price = $this->prices->priceOf($interval->product());

            return $this->start($by, $card, [
                'purpose' => PaymentPurpose::Subscription,
                'description' => config('app.name')." Business plan ({$interval->label()})",
                'details' => ['interval' => $interval->value],
                'amount' => $price->amount,
                'currency' => $price->currency,
            ]);
        });
    }

    public function buyEventCredits(ClientUser $by, int $quantity, ?PaymentMethod $card): Payment|Checkout
    {
        return $this->exclusively($by->client, function () use ($by, $quantity, $card) {
            $client = $by->client->refresh();

            if ($client->plan === ClientPlan::Enterprise || ($client->plan === ClientPlan::Business && $client->planActive())) {
                throw new PurchaseNotAllowedException("Your {$client->plan->label()} plan already includes unlimited events.");
            }

            $price = $this->prices->priceOf(BillingProduct::CelebrationEvent);

            return $this->start($by, $card, [
                'purpose' => PaymentPurpose::EventCredits,
                'description' => $quantity === 1 ? 'Celebration event' : "{$quantity} Celebration events",
                'details' => ['quantity' => $quantity],
                'amount' => $price->amount * $quantity,
                'currency' => $price->currency,
            ]);
        });
    }

    public function buyExtraGuests(ClientUser $by, Event $event, int $blocks, ?PaymentMethod $card): Payment|Checkout
    {
        return $this->exclusively($by->client, function () use ($by, $event, $blocks, $card) {
            $client = $by->client->refresh();

            if (! $client->plan->allowsExtraGuests()) {
                throw new PurchaseNotAllowedException("Extra guests can't be added on the {$client->plan->label()} plan. Upgrade to Celebration or Business.");
            }

            if (! $client->planActive()) {
                throw new PurchaseNotAllowedException("Your {$client->plan->label()} plan has ended. Renew it to add guests.");
            }

            $price = $this->prices->priceOf(BillingProduct::ExtraGuestBlock);
            $guests = $blocks * (int) config('emp.extra_guests_block');

            return $this->start($by, $card, [
                'purpose' => PaymentPurpose::ExtraGuests,
                'description' => mb_substr("{$guests} extra guests · {$event->title}", 0, 255),
                'details' => ['blocks' => $blocks, 'guests' => $guests],
                'amount' => $price->amount * $blocks,
                'currency' => $price->currency,
            ], $event);
        });
    }

    public function buyTemplate(ClientUser $by, Template $template, ?PaymentMethod $card): Payment|Checkout
    {
        return $this->exclusively($by->client, function () use ($by, $template, $card) {
            $client = $by->client;
            $template = $this->templates->findSelectable($template->id, $client);

            if ($template->isFree() || $this->templates->isUsableBy($template, $client)) {
                throw new PurchaseNotAllowedException("You can already use {$template->name}.");
            }

            return $this->start($by, $card, [
                'purpose' => PaymentPurpose::Template,
                'description' => mb_substr("Invitation design: {$template->name}", 0, 255),
                'details' => [],
                'amount' => $template->price,
                'currency' => $template->currency,
            ], $template);
        });
    }

    public function renewEnterprise(ClientUser $by, ?PaymentMethod $card): Payment|Checkout
    {
        return $this->exclusively($by->client, function () use ($by, $card) {
            $client = $by->client->refresh();

            if ($client->plan !== ClientPlan::Enterprise) {
                throw new PurchaseNotAllowedException('Only an Enterprise plan is renewed this way.');
            }

            if ($client->planActive()) {
                throw new PurchaseNotAllowedException("Your Enterprise plan is active until {$client->plan_expires_at?->format('j M Y')}.");
            }

            if (! $client->canRenewOnline()) {
                throw new PurchaseNotAllowedException('Your Enterprise renewal is arranged with our team. Get in touch to renew.');
            }

            $months = (int) $client->renewal_months;

            return $this->start($by, $card, [
                'purpose' => PaymentPurpose::EnterpriseRenewal,
                'description' => config('app.name')." Enterprise plan ({$months} ".($months === 1 ? 'month' : 'months').')',
                'details' => ['months' => $months],
                'amount' => (int) $client->renewal_price,
                'currency' => 'LKR',
            ]);
        });
    }

    public function addCard(ClientUser $by): Checkout
    {
        /** @var Checkout */
        return $this->start($by, null, [
            'purpose' => PaymentPurpose::CardSetup,
            'description' => 'Save a card for '.config('app.name'),
            'details' => [],
            'amount' => self::CARD_SETUP_AMOUNT,
            'currency' => 'LKR',
        ]);
    }

    public function redirect(Checkout $checkout): GatewayRedirect
    {
        if (! $checkout->channel->redirects() || $checkout->payment_method_id !== null) {
            throw new PurchaseNotAllowedException('This checkout is no longer waiting for a card.');
        }

        $client = $checkout->client;
        $payer = $checkout->initiator ?? $client->owner;
        $details = BillingDetailsData::fromStored($client->billing_details);

        if ($payer === null || $details === null) {
            throw new BillingDetailsMissingException;
        }

        return $this->gateway->redirect($checkout, PayerData::for($payer, $details));
    }

    public function saveBillingDetails(Client $client, BillingDetailsData $details): void
    {
        $this->clients->update($client, ['billing_details' => $details->toArray()]);
    }

    /**
     * Opens the checkout, and charges the saved card right away when there is one.
     *
     * @param  array{purpose: PaymentPurpose, description: string, details: array<string, mixed>, amount: int, currency: string}  $order
     */
    private function start(ClientUser $by, ?PaymentMethod $card, array $order, ?Model $subject = null): Payment|Checkout
    {
        if ($card === null) {
            if (BillingDetailsData::fromStored($by->client->billing_details) === null) {
                throw new BillingDetailsMissingException;
            }
        } elseif ($card->client_id !== $by->client_id || ! $card->isChargeable()) {
            throw new PaymentMethodUnavailableException;
        }

        $channel = match (true) {
            $card !== null => PaymentChannel::SavedCard,
            $order['purpose']->savesCard() => PaymentChannel::Preapproval,
            default => PaymentChannel::Checkout,
        };

        /** @var Checkout $checkout */
        $checkout = $this->checkouts->create([
            ...$order,
            'client_id' => $by->client_id,
            'order_id' => Checkout::newOrderId(),
            'channel' => $channel,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payment_method_id' => $card?->id,
            'initiated_by' => $by->id,
        ]);

        return $card !== null ? $this->paymentService->chargeSavedCard($checkout, $card) : $checkout;
    }

    private function ensureCanSubscribe(Client $client): void
    {
        if ($client->plan === ClientPlan::Enterprise) {
            throw new PurchaseNotAllowedException('Enterprise accounts are billed by our team. Get in touch to change your plan.');
        }

        $subscription = $this->subscriptions->current($client);

        if ($subscription !== null && $subscription->isRunning()) {
            throw new PurchaseNotAllowedException($subscription->status === SubscriptionStatus::Canceled
                ? 'Your subscription is still running. Turn auto-renew back on instead of subscribing again.'
                : 'You\'re already subscribed to Business.');
        }

        // An active plan isn't renewed early: Business is paid again once it has ended.
        if ($client->plan === ClientPlan::Business && $client->planActive()) {
            throw new PurchaseNotAllowedException($client->plan_expires_at === null
                ? 'Your Business plan has no end date, so there\'s nothing to pay.'
                : "Your Business plan is active until {$client->plan_expires_at->format('j M Y')}.");
        }
    }

    /**
     * One purchase at a time per account: a double click can't charge a saved card twice,
     * and the rules are checked against what the previous purchase changed.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function exclusively(Client $client, callable $callback): mixed
    {
        $lock = Cache::lock("billing.checkout.{$client->id}", 60);

        if (! $lock->get()) {
            throw new PurchaseNotAllowedException('Another payment is in progress. Wait a moment and try again.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
