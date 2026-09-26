<?php

namespace App\Domains\Rsvp\Http\Resources;

use App\Domains\Guest\Http\Resources\GuestResource;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client dashboard representation. Includes the RSVP link so the client
 * can share it manually.
 *
 * @mixin Rsvp
 */
class RsvpResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'guest_id' => $this->guest_id,
            'status' => $this->status->value,
            'rsvp_url' => $this->rsvpUrl(),
            'is_expired' => $this->isExpired(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'reminder_count' => $this->reminder_count,
            'last_reminded_at' => $this->last_reminded_at?->toIso8601String(),
            'message' => $this->whenLoaded('latestNotification', fn () => $this->latestNotification ? [
                'status' => $this->latestNotification->status->value,
                'error' => $this->latestNotification->error,
                'sent_at' => $this->latestNotification->sent_at?->toIso8601String(),
                'delivered_at' => $this->latestNotification->delivered_at?->toIso8601String(),
                'read_at' => $this->latestNotification->read_at?->toIso8601String(),
                'updated_at' => $this->latestNotification->updated_at->toIso8601String(),
            ] : null),
            'guest' => GuestResource::make($this->whenLoaded('guest')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
