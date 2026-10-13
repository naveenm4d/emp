<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\CheckoutRepositoryInterface;
use App\Domains\Billing\Contracts\CheckoutServiceInterface;
use App\Domains\Billing\Contracts\PaymentRepositoryInterface;
use App\Domains\Billing\Http\Resources\CheckoutResource;
use App\Domains\Billing\Models\Checkout;
use App\Domains\Billing\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hand-off to PayHere (a signed form posted on load), and where PayHere
 * sends the payer back. That page never changes anything: it shows the
 * payment once PayHere's notification recorded it, and waits until then.
 */
class CheckoutController extends InertiaController
{
    use ResolvesClient;

    public function pay(Checkout $checkout, CheckoutServiceInterface $service): Response|RedirectResponse
    {
        $this->authorize('view', $checkout);

        $redirect = $service->redirect($checkout);

        if ($redirect->method === 'get') {
            return redirect()->to($redirect->url);
        }

        return Inertia::render('client/billing/redirect', [
            'checkout' => CheckoutResource::make($checkout),
            'gateway' => ['url' => $redirect->url, 'fields' => $redirect->fields],
        ]);
    }

    public function show(
        Request $request,
        string $order,
        PaymentRepositoryInterface $payments,
        CheckoutRepositoryInterface $checkouts,
    ): Response|RedirectResponse {
        $this->authorize('viewAny', Payment::class);
        $clientId = $this->clientUser($request)->client_id;

        $payment = $payments->findByOrderId($order);
        if ($payment !== null && $payment->client_id === $clientId) {
            return to_route('client.billing.payments.show', $payment);
        }

        $checkout = $checkouts->findByOrderId($order);

        return Inertia::render('client/billing/checkout', [
            // null: never processed and no longer waiting (canceled or abandoned).
            'checkout' => $checkout !== null && $checkout->client_id === $clientId ? CheckoutResource::make($checkout) : null,
            'canceled' => $request->boolean('canceled'),
        ]);
    }
}
