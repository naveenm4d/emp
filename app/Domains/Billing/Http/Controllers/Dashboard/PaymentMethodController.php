<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\CheckoutServiceInterface;
use App\Domains\Billing\Contracts\PaymentMethodServiceInterface;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Saved cards: add one on PayHere's page, choose the default, remove one. */
class PaymentMethodController extends InertiaController
{
    use ResolvesClient;

    public function __construct(
        private readonly PaymentMethodServiceInterface $cards,
    ) {}

    public function store(Request $request, CheckoutServiceInterface $checkout): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        return to_route('client.billing.checkouts.pay', $checkout->addCard($this->clientUser($request)));
    }

    public function update(PaymentMethod $card): RedirectResponse
    {
        $this->authorize('update', $card);

        $this->cards->makeDefault($card);

        return $this->backWithSuccess("{$card->label()} is now your default card.");
    }

    public function destroy(PaymentMethod $card): RedirectResponse
    {
        $this->authorize('delete', $card);

        $warning = $this->cards->remove($card);

        return $warning !== null
            ? back()->with('error', $warning)
            : $this->backWithSuccess("{$card->label()} removed.");
    }
}
