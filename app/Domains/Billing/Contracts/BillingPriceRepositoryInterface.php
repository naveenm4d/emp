<?php

namespace App\Domains\Billing\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Billing\Enums\BillingProduct;
use App\Domains\Billing\Models\BillingPrice;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<BillingPrice>
 */
interface BillingPriceRepositoryInterface extends RepositoryInterface
{
    /** @return Collection<int, BillingPrice> */
    public function all(): Collection;

    public function findByProduct(BillingProduct $product): ?BillingPrice;
}
