<?php

namespace App\Domains\Billing\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\CheckoutServiceInterface;
use App\Domains\Billing\Http\Controllers\Dashboard\Concerns\RespondsWithPayment;
use App\Domains\Billing\Http\Requests\Dashboard\BuyEventCreditsRequest;
use App\Domains\Billing\Http\Requests\Dashboard\BuyExtraGuestsRequest;
use App\Domains\Billing\Http\Requests\Dashboard\BuyTemplateRequest;
use App\Domains\Billing\Http\Requests\Dashboard\RenewEnterpriseRequest;
use App\Domains\Billing\Http\Requests\Dashboard\SubscribeRequest;
use App\Domains\Billing\Http\Requests\Dashboard\UpdateBillingDetailsRequest;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Models\Template;
use Illuminate\Http\RedirectResponse;

/**
 * Buying: the Business subscription, Celebration event credits, extra guests
 * and paid designs. With a saved card the charge happens here; otherwise the
 * payer continues on PayHere's page.
 */
class PurchaseController extends InertiaController
{
    use ResolvesClient, RespondsWithPayment;

    public function __construct(
        private readonly CheckoutServiceInterface $checkout,
    ) {}

    public function subscribe(SubscribeRequest $request): RedirectResponse
    {
        return $this->afterPurchase($this->checkout->subscribe($this->clientUser($request), $request->billingInterval(), $request->card()));
    }

    public function eventCredits(BuyEventCreditsRequest $request): RedirectResponse
    {
        return $this->afterPurchase($this->checkout->buyEventCredits($this->clientUser($request), $request->quantity(), $request->card()));
    }

    public function extraGuests(BuyExtraGuestsRequest $request, Event $event): RedirectResponse
    {
        return $this->afterPurchase($this->checkout->buyExtraGuests($this->clientUser($request), $event, $request->blocks(), $request->card()));
    }

    public function template(BuyTemplateRequest $request, Template $template): RedirectResponse
    {
        return $this->afterPurchase($this->checkout->buyTemplate($this->clientUser($request), $template, $request->card()));
    }

    public function renewEnterprise(RenewEnterpriseRequest $request): RedirectResponse
    {
        return $this->afterPurchase($this->checkout->renewEnterprise($this->clientUser($request), $request->card()));
    }

    public function details(UpdateBillingDetailsRequest $request): RedirectResponse
    {
        $this->checkout->saveBillingDetails($this->client($request), $request->toData());

        return $this->backWithSuccess('Billing details saved.');
    }
}
