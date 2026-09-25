<?php

namespace App\Domains\Guest\Http\Resources;

use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Http\Resources\InvitationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Guest
 */
class GuestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'source' => $this->source->value,
            'approval_status' => $this->approval_status->value,
            'rsvp_status' => $this->rsvp_status->value,
            'check_in_status' => $this->check_in_status->value,
            'latest_invitation' => InvitationResource::make($this->whenLoaded('latestInvitation')),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
