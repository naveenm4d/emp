<?php

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\BillingProduct;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a product costs; staff change it in the admin console. Payments keep
 * their own copy of the amount, so a price change never alters a payment.
 *
 * @property int $id
 * @property BillingProduct $product
 * @property int $amount minor units
 * @property string $currency
 * @property bool $is_active inactive = not sold
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['product', 'amount', 'currency', 'is_active'])]
class BillingPrice extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'product' => BillingProduct::class,
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
