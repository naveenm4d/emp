<?php

namespace App\Domains\Invitation\Http\Resources;

use App\Domains\Event\Http\Resources\PublicEventResource;
use App\Domains\Guest\Http\Resources\PublicGuestResource;
use App\Domains\Invitation\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Guest-facing RSVP view. Never exposes the token.
 *
 * @mixin Invitation
 */
class RsvpResource extends JsonResource
{
    /** @var array<string, mixed>|null the generated invitation for this guest */
    private ?array $design = null;

    /** @param array<string, mixed> $design */
    public static function withDesign(Invitation $invitation, array $design): self
    {
        $resource = new self($invitation);
        $resource->design = $design;

        return $resource;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'is_expired' => $this->isExpired(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'event' => PublicEventResource::make($this->whenLoaded('event')),
            'guest' => PublicGuestResource::make($this->whenLoaded('guest')),
            'design' => $this->when($this->design !== null, fn () => $this->design),
        ];
    }
}
