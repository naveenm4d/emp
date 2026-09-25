<?php

namespace App\Domains\Guest\Http\Requests\Site;

use App\Domains\Guest\DTOs\GuestData;
use App\Domains\Guest\Http\Requests\GuestRules;
use Illuminate\Foundation\Http\FormRequest;

class RegisterGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...GuestRules::contact(),
            // A public registrant must leave a way to be contacted.
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
        ];
    }

    public function toData(): GuestData
    {
        return GuestData::fromArray($this->safe()->only(['name', 'email', 'phone']));
    }
}
