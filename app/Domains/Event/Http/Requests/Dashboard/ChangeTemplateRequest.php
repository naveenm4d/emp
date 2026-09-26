<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

/** Switching an event to another design from the Design tab. */
class ChangeTemplateRequest extends UpdateEventRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'uuid', $this->selectableTemplate(...)],
        ];
    }
}
