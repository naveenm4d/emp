<?php

namespace App\Domains\Billing\Services;

use App\Core\Services\BaseService;
use App\Domains\Billing\Contracts\BillingPriceRepositoryInterface;
use App\Domains\Billing\Contracts\PriceServiceInterface;
use App\Domains\Billing\Enums\BillingProduct;
use App\Domains\Billing\Exceptions\PriceUnavailableException;
use App\Domains\Billing\Models\BillingPrice;
use App\Domains\Billing\Support\Money;
use App\Domains\Staff\Contracts\StaffActivityServiceInterface;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Support\Collection;

class PriceService extends BaseService implements PriceServiceInterface
{
    public function __construct(
        private readonly BillingPriceRepositoryInterface $prices,
        private readonly StaffActivityServiceInterface $activities,
    ) {}

    public function all(): Collection
    {
        return $this->prices->all();
    }

    public function priceOf(BillingProduct $product, bool $onSale = true): BillingPrice
    {
        $price = $this->prices->findByProduct($product);

        if ($price === null || ($onSale && ! $price->is_active)) {
            throw new PriceUnavailableException;
        }

        return $price;
    }

    public function catalogue(): array
    {
        return $this->prices->all()
            ->mapWithKeys(fn (BillingPrice $price) => [$price->product->value => [
                'amount' => $price->amount,
                'currency' => $price->currency,
                'is_active' => $price->is_active,
            ]])
            ->all();
    }

    public function update(BillingProduct $product, int $amount, bool $isActive, StaffMember $by, string $note): BillingPrice
    {
        return $this->transaction(function () use ($product, $amount, $isActive, $by, $note) {
            $price = $this->priceOf($product, onSale: false);
            $before = ['amount' => $price->amount, 'is_active' => $price->is_active];

            /** @var BillingPrice $price */
            $price = $this->prices->update($price, ['amount' => $amount, 'is_active' => $isActive]);

            $this->activities->record(
                staff: $by,
                action: 'admin.billing.prices.update',
                description: "Price of {$product->label()}: ".Money::format($before['amount'], $price->currency)
                    .' → '.Money::format($price->amount, $price->currency)
                    .($price->is_active ? '' : ' (not for sale)'),
                // Prices have integer keys (the log's subject is a UUID): the product goes in the changes.
                changes: ['product' => $product->value, 'before' => $before, 'after' => ['amount' => $price->amount, 'is_active' => $price->is_active]],
                note: $note,
            );

            return $price;
        });
    }
}
