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
            'created_at' => $this->whenHas('created_at', fn () => $this->created_at->toIso8601String()),
        ];
    }
}
