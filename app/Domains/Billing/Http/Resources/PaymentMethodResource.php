<?php

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A saved card as the dashboard shows it: masked, never the token.
 *
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'brand' => $this->brand,
            'last4' => $this->last4,
            'card_holder_name' => $this->card_holder_name,
            'expiry' => $this->expiry_month && $this->expiry_year
                ? sprintf('%02d/%02d', $this->expiry_month, $this->expiry_year % 100)
                : null,
            'is_default' => $this->is_default,
            'is_expired' => $this->isExpired(),
            'removed' => $this->trashed(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
