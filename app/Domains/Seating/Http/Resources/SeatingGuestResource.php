<?php

namespace App\Domains\Seating\Http\Resources;

use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\Models\SeatAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A guest as the seating page needs them: party, status and where they sit.
 *
 * @mixin Guest
 */
class SeatingGuestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lead = $this->seats->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'rsvp_status' => $this->rsvp_status->value,
            'additional_guests' => $this->additional_guests,
            'children' => $this->children,
            'party_size' => $this->partySize(),
            'dietary_restrictions' => $this->dietary_restrictions ?? [],
            'dietary_notes' => $this->dietary_notes,
            'seated' => $this->seats->count(),
            'table_id' => $lead?->table_id,
            'table_name' => $lead?->table->name,
            'seat_numbers' => $this->seats->pluck('seat_number')->all(),
            // Every seated person of the party, wherever they sit (a party can be split).
            'seats' => $this->seats->map(fn (SeatAssignment $seat) => [
                'party_member' => $seat->party_member,
                'label' => $this->partyMemberLabel($seat->party_member),
                'table_id' => $seat->table_id,
                'table_name' => $seat->table->name,
                'seat_number' => $seat->seat_number,
            ])->values()->all(),
            'missing_members' => array_map(
                fn (int $member) => ['party_member' => $member, 'label' => $this->partyMemberLabel($member)],
                array_values(array_diff(range(0, $this->partySize() - 1), $this->seats->pluck('party_member')->all())),
            ),
        ];
    }
}
