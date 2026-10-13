<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\Http\Requests\Dashboard\UpdateSubscriptionRequest;
use App\Domains\Billing\Models\Subscription;
use Illuminate\Http\RedirectResponse;

/** The Business subscription: auto-renew off / on, monthly ↔ yearly, the card it uses. */
class SubscriptionController extends InertiaController
{
    use ResolvesClient;

    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptions,
    ) {}

    public function update(UpdateSubscriptionRequest $request): RedirectResponse
    {
        $subscription = $this->subscriptions->current($this->client($request));
        abort_if($subscription === null, 404);
        $this->authorize('update', $subscription);

        return match ($request->action()) {
            'cancel' => $this->cancel($subscription),
            'resume' => $this->backWithSuccess('Auto-renew is on again. Your plan renews on '
                .$this->subscriptions->resume($subscription)->current_period_end->format('j M Y').'.'),
            'interval' => $this->switchInterval($request, $subscription),
            default => $this->useCard($request, $subscription),
        };
    }

    private function cancel(Subscription $subscription): RedirectResponse
    {
        $subscription = $this->subscriptions->cancel($subscription);

        return $this->backWithSuccess("Auto-renew is off. Business stays until {$subscription->current_period_end->format('j M Y')}.");
    }

    private function switchInterval(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $subscription = $this->subscriptions->switchInterval($subscription, $request->billingInterval());

        return $this->backWithSuccess("From {$subscription->current_period_end->format('j M Y')} you'll be billed {$subscription->renewalInterval()->value}.");
    }

    private function useCard(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $card = $this->subscriptions->useCard($subscription, $request->card())->paymentMethod;

        return $this->backWithSuccess("Renewals will be charged to {$card?->label()}.");
    }
}
