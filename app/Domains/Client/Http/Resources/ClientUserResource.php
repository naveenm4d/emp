<?php

namespace App\Domains\Client\Http\Resources;

use App\Domains\Client\Models\ClientUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClientUser
 */
class ClientUserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'role' => $this->role->value,
            'is_owner' => $this->isOwner(),
            'pending' => $this->isPending(),
            'permissions' => $this->effectivePermissions(),
            'all_events' => $this->seesAllEvents(),
            'event_ids' => $this->whenLoaded('events', fn () => $this->events->pluck('id')->values()->all()),
            'invited_at' => $this->invited_at?->toIso8601String(),
            'joined_at' => $this->joined_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
