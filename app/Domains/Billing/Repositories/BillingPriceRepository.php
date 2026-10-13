<?php

namespace App\Domains\Billing\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Billing\Contracts\BillingPriceRepositoryInterface;
use App\Domains\Billing\Enums\BillingProduct;
use App\Domains\Billing\Models\BillingPrice;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<BillingPrice>
 */
class BillingPriceRepository extends BaseRepository implements BillingPriceRepositoryInterface
{
    protected function model(): string
    {
        return BillingPrice::class;
    }

    public function all(): Collection
    {
        return $this->query()->orderBy('id')->get();
    }

    public function findByProduct(BillingProduct $product): ?BillingPrice
    {
        return $this->query()->where('product', $product)->first();
    }
}
