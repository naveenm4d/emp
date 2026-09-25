<?php

namespace App\Domains\Notification\Http\Resources;

use App\Domains\Notification\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'guest_id' => $this->guest_id,
            'channel' => $this->channel->value,
            'status' => $this->status->value,
            'recipient' => $this->recipient,
            'message' => $this->message,
            'error' => $this->error,
            'attempts' => $this->attempts,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'guest' => $this->whenLoaded('guest', fn () => $this->guest ? ['id' => $this->guest->id, 'name' => $this->guest->name] : null),
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'client' => $this->event->relationLoaded('client') ? ['id' => $this->event->client->id, 'name' => $this->event->client->name] : null,
            ]),
            'deliveries' => MessageDeliveryResource::collection($this->whenLoaded('deliveries')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
