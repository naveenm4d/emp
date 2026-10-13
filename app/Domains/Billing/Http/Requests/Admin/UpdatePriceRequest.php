<?php

namespace App\Domains\Billing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Staff changing a price (in rupees here, stored in minor units); a note is required. */
class UpdatePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:billing.prices` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:10', 'max:10000000', 'decimal:0,2'],
            'is_active' => ['required', 'boolean'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.min' => 'PayHere takes at least Rs. 10.',
            'note.required' => 'Add a note: why the price changed.',
        ];
    }

    /** The amount in minor units. */
    public function amount(): int
    {
        return (int) round(((float) $this->validated('amount')) * 100);
    }

    public function isActive(): bool
    {
        return (bool) $this->validated('is_active');
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
