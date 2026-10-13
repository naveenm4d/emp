<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\Enums\BillingProduct;
use App\Domains\Billing\Exceptions\PriceUnavailableException;
use App\Domains\Billing\Models\BillingPrice;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Support\Collection;

/** What products cost; staff change prices in the admin console. */
interface PriceServiceInterface
{
    /** @return Collection<int, BillingPrice> */
    public function all(): Collection;

    /**
     * The product's price.
     *
     * @throws PriceUnavailableException when it isn't sold (inactive) and $onSale is required
     */
    public function priceOf(BillingProduct $product, bool $onSale = true): BillingPrice;

    /**
     * Prices for the dashboard and the public pricing section.
     *
     * @return array<string, array{amount: int, currency: string, is_active: bool}> keyed by product
     */
    public function catalogue(): array;

    /** Staff change a price; logged with before / after and the note. */
    public function update(BillingProduct $product, int $amount, bool $isActive, StaffMember $by, string $note): BillingPrice;
}
