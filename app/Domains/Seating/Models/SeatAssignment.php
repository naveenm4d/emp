<?php

namespace App\Domains\Seating\Models;

use App\Domains\Guest\Models\Guest;
use Carbon\CarbonImmutable;
use Database\Factories\SeatAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person in one seat: the guest (party_member 0), one of their plus-ones
 * (1..additional_guests) or one of their children (after the plus-ones).
 *
 * @property string $id
 * @property string $event_id
 * @property string $table_id
 * @property string $guest_id
 * @property int $party_member
 * @property int $seat_number
 * @property-read EventTable $table
 * @property-read Guest $guest
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(SeatAssignmentFactory::class)]
#[Fillable(['event_id', 'table_id', 'guest_id', 'party_member', 'seat_number'])]
class SeatAssignment extends Model
{
    /** @use HasFactory<SeatAssignmentFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'party_member' => 'integer',
            'seat_number' => 'integer',
        ];
    }

    /** Who sits here: "John", "John's guest" or "John's child 2". */
    public function label(): string
    {
        return $this->guest->partyMemberLabel($this->party_member);
    }

    /** @return BelongsTo<EventTable, $this> */
    public function table(): BelongsTo
    {
        return $this->belongsTo(EventTable::class, 'table_id');
    }

    /** @return BelongsTo<Guest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
