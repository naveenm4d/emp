<?php

namespace App\Domains\Seating\Http\Requests\Dashboard;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Two guests of the event, for swapping or replacing seats.
 */
class SeatingPairRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('manageSeating', $this->event()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $ofEvent = Rule::exists('guests', 'id')->where('event_id', $this->event()->id);

        return [
            'guest_id' => ['required', 'uuid', $ofEvent],
            'other_guest_id' => ['required', 'uuid', 'different:guest_id', $ofEvent],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.exists' => 'Pick a guest of this event.',
            'other_guest_id.different' => 'Pick another guest.',
        ];
    }

    public function guest(): Guest
    {
        return Guest::query()->findOrFail((string) $this->validated('guest_id'));
    }

    public function otherGuest(): Guest
    {
        return Guest::query()->findOrFail((string) $this->validated('other_guest_id'));
    }

    private function event(): Event
    {
        /** @var Event */
        return $this->route('event');
    }
}
