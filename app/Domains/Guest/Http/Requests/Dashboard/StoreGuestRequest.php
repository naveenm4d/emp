<?php

namespace App\Domains\Guest\Http\Requests\Dashboard;

use App\Domains\Guest\DTOs\GuestData;
use App\Domains\Guest\Http\Requests\GuestRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('event')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...GuestRules::contact(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): GuestData
    {
        return GuestData::fromArray($this->validated());
    }
}
