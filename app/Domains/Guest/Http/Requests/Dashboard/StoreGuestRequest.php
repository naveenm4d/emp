<?php

namespace App\Domains\Guest\Http\Requests\Dashboard;

use App\Domains\Guest\DTOs\GuestData;
use App\Domains\Guest\Http\Requests\GuestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('manageGuests', $this->route('event')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...GuestRules::contact(),
            ...GuestRules::invitedParty(),
            'email' => ['required_without:phone', ...GuestRules::contact()['email']],
            'notes' => ['nullable', 'string', 'max:2000'],
            'invitation_message' => ['nullable', 'string', 'max:1000'],
            'reminder_message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.required_without' => GuestRules::CONTACT_REQUIRED];
    }

    public function toData(): GuestData
    {
        return GuestData::fromArray($this->validated());
    }
}
