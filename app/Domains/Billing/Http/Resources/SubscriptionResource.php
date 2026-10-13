<?php

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => $this->plan->value,
            'interval' => $this->interval->value,
            'next_interval' => $this->next_interval?->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'auto_renew' => $this->auto_renew,
            'running' => $this->isRunning(),
            'card' => $this->paymentMethod && ! $this->paymentMethod->trashed()
                ? PaymentMethodResource::make($this->paymentMethod)->resolve($request)
                : null,
            'current_period_start' => $this->current_period_start->toIso8601String(),
            'current_period_end' => $this->current_period_end->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ended_reason' => $this->ended_reason,
        ];
    }
}
