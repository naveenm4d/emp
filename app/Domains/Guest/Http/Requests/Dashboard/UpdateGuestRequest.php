<?php

namespace App\Domains\Guest\Http\Requests\Dashboard;

use App\Domains\Guest\DTOs\UpdateGuestData;
use App\Domains\Guest\Http\Requests\GuestRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('guest')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return collect([...GuestRules::contact(), 'notes' => ['nullable', 'string', 'max:2000']])
            ->map(fn (array $rules) => ['sometimes', ...$rules])
            ->all();
    }

    public function toData(): UpdateGuestData
    {
        return UpdateGuestData::fromArray($this->validated());
    }
}
