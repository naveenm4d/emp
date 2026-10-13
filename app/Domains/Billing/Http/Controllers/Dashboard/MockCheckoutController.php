<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Contracts\PaymentServiceInterface;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Gateways\MockPaymentGateway;
use App\Domains\Billing\Http\Resources\CheckoutResource;
use App\Domains\Billing\Models\Checkout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The mock gateway's "PayHere page" for local runs without credentials: the
 * tester picks the outcome, which goes through the same notification
 * handling as PayHere's. Unavailable in production and with real credentials.
 */
class MockCheckoutController extends InertiaController
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function show(Checkout $checkout): Response
    {
        $this->ensureAvailable($checkout);

        return Inertia::render('client/billing/mock', ['checkout' => CheckoutResource::make($checkout)]);
    }

    public function complete(Request $request, Checkout $checkout, PaymentServiceInterface $payments): RedirectResponse
    {
        $this->ensureAvailable($checkout);

        $outcome = $request->validate(['outcome' => ['required', 'in:succeed,decline_later,fail,cancel']])['outcome'];

        $payments->handleNotification(MockPaymentGateway::notification($checkout, match ($outcome) {
            'succeed', 'decline_later' => PaymentStatus::Succeeded,
            'cancel' => PaymentStatus::Canceled,
            default => PaymentStatus::Failed,
        }, declineLater: $outcome === 'decline_later'));

        return to_route('client.billing.orders.show', ['order' => $checkout->order_id, 'canceled' => $outcome === 'cancel' ? 1 : null]);
    }

    private function ensureAvailable(Checkout $checkout): void
    {
        abort_unless($this->gateway->isMock() && ! app()->isProduction(), 404);
        $this->authorize('view', $checkout);
    }
}
