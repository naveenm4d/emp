<?php

namespace App\Domains\Seating\Http\Resources;

use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Models\SeatAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A table with every seat, taken or not.
 *
 * @mixin EventTable
 */
class EventTableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $taken = $this->seats->keyBy('seat_number');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'seat_count' => $this->seat_count,
            'shape' => $this->shape->value,
            'seats' => array_map(function (int $number) use ($taken) {
                /** @var SeatAssignment|null $seat */
                $seat = $taken->get($number);

                return [
                    'number' => $number,
                    'guest_id' => $seat?->guest_id,
                    'party_member' => $seat?->party_member,
                    'label' => $seat?->label(),
                    'status' => $seat?->guest->rsvp_status->value,
                ];
            }, range(1, $this->seat_count)),
        ];
    }
}
