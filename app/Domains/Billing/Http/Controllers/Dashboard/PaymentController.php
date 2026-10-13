<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\BillingQueryServiceInterface;
use App\Domains\Billing\Http\Resources\PaymentResource;
use App\Domains\Billing\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Billing history (payments PayHere processed) and one payment's receipt. */
class PaymentController extends InertiaController
{
    use ResolvesClient;

    public function index(Request $request, BillingQueryServiceInterface $billing): Response
    {
        $this->authorize('viewAny', Payment::class);

        return Inertia::render('client/billing/payments', [
            'payments' => PaymentResource::collection($billing->payments($this->client($request))),
        ]);
    }

    public function show(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        return Inertia::render('client/billing/payment', [
            'payment' => PaymentResource::make($payment->load('paymentMethod')),
        ]);
    }
}
