<?php

namespace App\Domains\Guest\Http\Resources;

use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Http\Resources\RsvpResource;
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
            'invitation_message' => $this->invitation_message,
            'reminder_message' => $this->reminder_message,
            'source' => $this->source->value,
            'approval_status' => $this->approval_status->value,
            'approval_status_changed_at' => $this->approval_status_changed_at?->toIso8601String(),
            'rsvp_status' => $this->rsvp_status->value,
            'check_in_status' => $this->check_in_status->value,
            'address' => $this->address,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'invited_additional_guests' => $this->invited_additional_guests,
            'invited_children' => $this->invited_children,
            'additional_guests' => $this->additional_guests,
            'children' => $this->children,
            'party_size' => $this->partySize(),
            'dietary_restrictions' => $this->dietary_restrictions ?? [],
            'dietary_notes' => $this->dietary_notes,
            'link_url' => $this->whenLoaded('link', fn () => $this->link?->url()),
            'link_open_count' => $this->whenLoaded('link', fn () => $this->link->open_count ?? 0),
            'link_last_opened_at' => $this->whenLoaded('link', fn () => $this->link?->last_opened_at?->toIso8601String()),
            'latest_rsvp' => RsvpResource::make($this->whenLoaded('latestRsvp')),
            // Invitations / reminders sent so far (not counting failed ones), against the event's limits.
            'messages_sent' => $this->when($this->invitations_sent !== null, fn () => [
                'invitations' => (int) $this->invitations_sent,
                'reminders' => (int) $this->reminders_sent,
            ]),
            // Where the party sits (seating), or null.
            'seating' => $this->whenLoaded('seats', fn () => $this->seats->isEmpty() ? null : [
                'table' => $this->seats->first()->table->name,
                'seats' => $this->seats->pluck('seat_number')->sort()->values()->all(),
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
