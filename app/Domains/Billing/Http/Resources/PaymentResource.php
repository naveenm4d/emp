<?php

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_message' => $this->status_message,
            'description' => $this->description,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'card' => $this->paymentMethod?->label(),
            'payhere_payment_id' => $this->payhere_payment_id,
            'automatic' => $this->initiated_by === null,
            'fulfilled' => $this->fulfilled_at !== null,
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'name' => $this->client->name, 'email' => $this->client->email]),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
