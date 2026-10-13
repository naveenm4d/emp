<?php

namespace App\Domains\Billing\Services;

use App\Core\Services\BaseService;
use App\Domains\Billing\Contracts\PaymentMethodRepositoryInterface;
use App\Domains\Billing\Contracts\PaymentMethodServiceInterface;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\DTOs\CardDetails;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Exceptions\PaymentMethodUnavailableException;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Models\Client;
use Illuminate\Support\Collection;

class PaymentMethodService extends BaseService implements PaymentMethodServiceInterface
{
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $cards,
        private readonly SubscriptionServiceInterface $subscriptions,
    ) {}

    public function forClient(Client $client): Collection
    {
        return $this->cards->forClient($client->id);
    }

    public function store(Client $client, CardDetails $card, ?string $addedBy): PaymentMethod
    {
        return $this->transaction(function () use ($client, $card, $addedBy) {
            $existing = $this->cards->forClient($client->id)->first(fn (PaymentMethod $saved) => $card->last4 !== null
                && $saved->last4 === $card->last4
                && $saved->brand === $card->brand
                && $saved->expiry_month === $card->expiryMonth
                && $saved->expiry_year === $card->expiryYear);

            $attributes = [
                'customer_token' => $card->token,
                'card_holder_name' => $card->holderName,
                'brand' => $card->brand,
                'last4' => $card->last4,
                'expiry_month' => $card->expiryMonth,
                'expiry_year' => $card->expiryYear,
            ];

            /** @var PaymentMethod $saved */
            $saved = $existing
                ? $this->cards->update($existing, $attributes)
                : $this->cards->create([...$attributes, 'client_id' => $client->id, 'added_by' => $addedBy]);

            $default = $this->cards->defaultFor($client->id);

            if ($default === null || ! $default->isChargeable()) {
                $this->makeDefault($saved);
            }

            return $saved;
        });
    }

    public function makeDefault(PaymentMethod $card): PaymentMethod
    {
        if (! $card->isChargeable()) {
            throw new PaymentMethodUnavailableException;
        }

        return $this->transaction(function () use ($card) {
            $this->cards->makeDefault($card);

            // Renewals follow the default card.
            $subscription = $this->subscriptions->current($card->client);
            if ($subscription !== null && $subscription->isRunning()) {
                $this->subscriptions->useCard($subscription, $card);
            }

            return $card;
        });
    }

    public function remove(PaymentMethod $card): ?string
    {
        return $this->transaction(function () use ($card) {
            $wasDefault = $card->is_default;

            $this->cards->update($card, ['customer_token' => null, 'is_default' => false]);
            $this->cards->delete($card);

            $replacement = $wasDefault
                ? $this->cards->forClient($card->client_id)->first(fn (PaymentMethod $other) => $other->isChargeable())
                : null;

            if ($replacement !== null) {
                $this->cards->makeDefault($replacement);
            }

            $subscription = $this->subscriptions->current($card->client);

            if ($subscription === null
                || $subscription->status !== SubscriptionStatus::Active
                || ! $subscription->isRunning()
                || $subscription->payment_method_id !== $card->id) {
                return null;
            }

            $next = $this->subscriptions->renewalCard($subscription);

            if ($next !== null) {
                $this->subscriptions->useCard($subscription, $next);

                return null;
            }

            $this->subscriptions->cancel($subscription);

            return "Auto-renew is now off: there's no card to charge. Add one before {$subscription->current_period_end->format('j M Y')} to keep Business.";
        });
    }
}
