<?php

namespace App\Domains\Billing\Http\Requests\Dashboard;

use App\Domains\Billing\DTOs\BillingDetailsData;
use App\Domains\Billing\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;

/** The account's billing contact; PayHere asks for it with every checkout. */
class UpdateBillingDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('create', Payment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a phone number, e.g. 077 123 4567.'];
    }

    public function toData(): BillingDetailsData
    {
        return BillingDetailsData::fromArray($this->validated());
    }
}
