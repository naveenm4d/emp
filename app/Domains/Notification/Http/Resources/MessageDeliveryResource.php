<?php

namespace App\Domains\Notification\Http\Resources;

use App\Domains\Notification\Models\MessageDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageDelivery
 */
class MessageDeliveryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'status' => $this->status,
            'raw_payload' => $this->raw_payload,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
