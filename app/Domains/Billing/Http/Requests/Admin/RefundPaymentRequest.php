<?php

namespace App\Domains\Billing\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Staff refunding a payment; the note says why (and goes to PayHere). */
class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:billing.refund` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Add a note: why the payment is refunded.'];
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
