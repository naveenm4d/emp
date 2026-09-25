<?php

namespace App\Domains\Guest\Http\Resources;

use App\Domains\Guest\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a guest sees about their own registration.
 *
 * @mixin Guest
 */
class PublicGuestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'approval_status' => $this->approval_status->value,
            'rsvp_status' => $this->rsvp_status->value,
        ];
    }
}
