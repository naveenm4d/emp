<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Event\DTOs\CreateEventData;

class StoreEventRequest extends EventRequest
{
    public function authorize(): bool
    {
        return $this->user('client') !== null;
    }

    public function toData(): CreateEventData
    {
        return CreateEventData::fromArray($this->validated());
    }
}
