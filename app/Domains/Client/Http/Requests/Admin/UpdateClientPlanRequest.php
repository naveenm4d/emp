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
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:1000', Rule::requiredIf($this->input('plan') === ClientPlan::Enterprise->value)],
            // Enterprise: the price and length of an online renewal (empty = staff renew by hand).
            'renewal_price' => ['nullable', 'numeric', 'min:10', 'max:100000000', 'decimal:0,2', 'required_with:renewal_months'],
            'renewal_months' => ['nullable', 'integer', 'min:1', 'max:36', 'required_with:renewal_price'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Add a note: why the plan changed (e.g. the payment reference).',
            'user_limit.required' => 'Enterprise accounts need a user limit.',
            'renewal_price.min' => 'PayHere takes at least Rs. 10.',
            'renewal_price.required_with' => 'Set the price of a renewal too.',
            'renewal_months.required_with' => 'Set how many months a renewal covers.',
        ];
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
