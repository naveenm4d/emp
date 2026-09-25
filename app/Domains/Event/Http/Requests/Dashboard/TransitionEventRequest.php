<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Event\Enums\EventState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('event')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['state' => ['required', Rule::enum(EventState::class)]];
    }

    public function state(): EventState
    {
        return EventState::from($this->validated('state'));
    }
}
