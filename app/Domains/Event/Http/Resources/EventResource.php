<?php

namespace App\Domains\Event\Http\Resources;

use App\Domains\Client\Http\Resources\ClientResource;
use App\Domains\Event\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full event representation for the client dashboard and staff console.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'state' => $this->state->value,
            'allowed_transitions' => array_map(fn ($s) => $s->value, $this->state->allowedTransitions()),
            'max_capacity' => $this->max_capacity,
            'require_approval' => $this->require_approval,
            'registration_open' => $this->registration_open,
            'event_type' => $this->event_type,
            'location_name' => $this->location_name,
            'location_address' => $this->location_address,
            'map_url' => $this->map_url,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'public_url' => route('site.events.show', $this->slug),
            'template_version_id' => $this->template_version_id,
            'template' => $this->whenLoaded('templateVersion', fn () => [
                'id' => $this->templateVersion->template_id,
                'key' => $this->templateVersion->template->key,
                'name' => $this->templateVersion->template->name,
                'version' => $this->templateVersion->version,
                'latest_version' => $this->templateVersion->template->latestVersion?->version,
                'has_update' => $this->templateVersion->template->latest_version_id !== $this->template_version_id,
            ]),
            'rendered_at' => $this->rendered_at?->toIso8601String(),
            'guests_count' => $this->whenCounted('guests'),
            'client' => ClientResource::make($this->whenLoaded('client')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
