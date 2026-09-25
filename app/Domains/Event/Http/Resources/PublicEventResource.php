<?php

namespace App\Domains\Event\Http\Resources;

use App\Domains\Event\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Guest-facing event representation (no ownership / internal fields).
 *
 * @mixin Event
 */
class PublicEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'event_type' => $this->event_type,
            'location_name' => $this->location_name,
            'location_address' => $this->location_address,
            'map_url' => $this->map_url,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'registration_open' => $this->acceptsPublicRegistrations(),
            'require_approval' => $this->require_approval,
        ];
    }
}
