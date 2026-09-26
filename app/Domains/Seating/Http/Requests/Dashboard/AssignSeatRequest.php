<?php

namespace App\Domains\Seating\Http\Requests\Dashboard;

use App\Domains\Guest\Models\Guest;
use App\Domains\Seating\Enums\SeatMode;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Seats a guest's party at the table from the chosen seat: the whole party
 * (party), as much of it as fits (split), its members still without a seat
 * (rest), or one person of it (member, with party_member).
 */
class AssignSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->table()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guest_id' => ['required', 'uuid', Rule::exists('guests', 'id')->where('event_id', $this->table()->event_id)],
            'seat_number' => ['required', 'integer', 'min:1', 'max:'.$this->table()->seat_count],
            'mode' => ['sometimes', Rule::enum(SeatMode::class)],
            'party_member' => ['required_if:mode,member', 'integer', 'min:0', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['guest_id.exists' => 'Pick a guest of this event.'];
    }

    public function guest(): Guest
    {
        return Guest::query()->findOrFail((string) $this->validated('guest_id'));
    }

    public function mode(): SeatMode
    {
        return SeatMode::tryFrom((string) $this->validated('mode')) ?? SeatMode::Party;
    }

    public function partyMember(): int
    {
        return (int) $this->validated('party_member');
    }

    public function seatNumber(): int
    {
        return (int) $this->validated('seat_number');
    }

    public function table(): EventTable
    {
        /** @var EventTable */
        return $this->route('table');
    }
}
