<?php

namespace App\Domains\Billing\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Billing\Contracts\PriceServiceInterface;
use App\Domains\Billing\Enums\BillingProduct;
use App\Domains\Billing\Http\Requests\Admin\UpdatePriceRequest;
use App\Domains\Billing\Http\Resources\BillingPriceResource;
use App\Domains\Billing\Support\Money;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Staff set what plans, event credits and extra guests cost. Paid designs are priced on each template. */
class PriceController extends InertiaController
{
    public function __construct(
        private readonly PriceServiceInterface $prices,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/billing/prices', [
            'prices' => BillingPriceResource::collection($this->prices->all()),
            'extraGuestsBlock' => (int) config('emp.extra_guests_block'),
        ]);
    }

    public function update(UpdatePriceRequest $request, BillingProduct $product): RedirectResponse
    {
        /** @var StaffMember $staff */
        $staff = $request->user('staff');

        $price = $this->prices->update($product, $request->amount(), $request->isActive(), $staff, $request->note());

        return $this->backWithSuccess("{$product->label()} now costs ".Money::format($price->amount, $price->currency).'.');
    }
}
