<?php

namespace App\Domains\Invitation\Http\Resources;

use App\Domains\Guest\Http\Resources\GuestResource;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Client dashboard representation. Includes the RSVP link so the client
 * can share it manually.
 *
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
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
            'guest' => GuestResource::make($this->whenLoaded('guest')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
