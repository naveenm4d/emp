<?php

namespace App\Domains\Client\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\BillingQueryServiceInterface;
use App\Domains\Billing\Contracts\PaymentMethodServiceInterface;
use App\Domains\Billing\Contracts\PriceServiceInterface;
use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\Http\Resources\PaymentMethodResource;
use App\Domains\Billing\Http\Resources\PaymentResource;
use App\Domains\Billing\Http\Resources\SubscriptionResource;
use App\Domains\Billing\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client's plan, what's used and what's included (shared `auth.client.plan`),
 * the prices, and for people who manage billing: the subscription, saved
 * cards, billing details and the latest payments.
 */
class MembershipController extends InertiaController
{
    use ResolvesClient;

    public function __invoke(
        Request $request,
        PriceServiceInterface $prices,
        SubscriptionServiceInterface $subscriptions,
        PaymentMethodServiceInterface $cards,
        BillingQueryServiceInterface $billing,
    ): Response {
        $client = $this->client($request);
        $canManage = $this->clientUser($request)->can('create', Payment::class);
        $subscription = $canManage ? $subscriptions->current($client)?->load('paymentMethod') : null;

        return Inertia::render('client/membership', [
            'prices' => $prices->catalogue(),
            'extraGuestsBlock' => (int) config('emp.extra_guests_block'),
            'billing' => $canManage ? [
                'subscription' => $subscription ? SubscriptionResource::make($subscription)->resolve($request) : null,
                'cards' => PaymentMethodResource::collection($cards->forClient($client))->resolve($request),
                'payments' => PaymentResource::collection($billing->recentPayments($client))->resolve($request),
                'details' => $client->billing_details,
                'mock' => $billing->usesMockGateway(),
            ] : null,
        ]);
    }
}
