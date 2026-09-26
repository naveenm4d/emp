<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Event\DTOs\CreateEventData;

class StoreEventRequest extends EventRequest
{
    public function authorize(): bool
    {
        return $this->user('client') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['event_date'][] = 'after_or_equal:today';

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'event_date.after_or_equal' => 'The event date must be today or later.',
        ];
    }

    public function toData(): CreateEventData
    {
        return CreateEventData::fromArray($this->validated());
    }
}
