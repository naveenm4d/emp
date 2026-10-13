<?php

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\Checkout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A checkout waiting for PayHere.
 *
 * @mixin Checkout
 */
class CheckoutResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'purpose' => $this->purpose->value,
            'purpose_label' => $this->purpose->label(),
            'channel' => $this->channel->value,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
