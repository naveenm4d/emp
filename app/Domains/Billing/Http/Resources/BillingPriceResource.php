<?php

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\BillingPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BillingPrice
 */
class BillingPriceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'product' => $this->product->value,
            'label' => $this->product->label(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
