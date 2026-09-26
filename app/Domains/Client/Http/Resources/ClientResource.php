<?php

namespace App\Domains\Client\Http\Resources;

use App\Domains\Client\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Client
 */
class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->whenHas('email_verified_at', fn () => $this->email_verified_at?->toIso8601String()),
            'events_count' => $this->whenCounted('events'),
            'plan' => $this->whenHas('plan', fn () => $this->plan->value),
            'plan_label' => $this->whenHas('plan', fn () => $this->plan->label()),
            'plan_active' => $this->whenHas('plan', fn () => $this->planActive()),
            // Staff only: the subscription end and unused event credits.
            'plan_expires_at' => $this->when($request->user('staff') !== null, fn () => $this->plan_expires_at?->toIso8601String()),
            'event_credits' => $this->when($request->user('staff') !== null, fn () => $this->event_credits),
            'created_at' => $this->whenHas('created_at', fn () => $this->created_at->toIso8601String()),
        ];
    }
}
