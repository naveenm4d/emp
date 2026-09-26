<?php

namespace App\Domains\Client\Http\Requests\Admin;

use App\Domains\Client\DTOs\ClientPlanData;
use App\Domains\Client\Enums\ClientPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Staff changing a client's plan after payment; a note saying why is required. */
class UpdateClientPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:clients.plan` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(ClientPlan::class)],
            'plan_expires_at' => ['nullable', 'date'],
            'event_credits' => ['required', 'integer', 'min:0', 'max:1000'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Add a note: why the plan changed (e.g. the payment reference).'];
    }

    public function toData(): ClientPlanData
    {
        return ClientPlanData::fromArray($this->validated());
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
