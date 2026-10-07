<?php

namespace App\Domains\Client\Http\Requests\Dashboard;

use App\Domains\Client\DTOs\UpdateProfileData;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(ClientUser::class)->ignore($this->user('client')?->getAuthIdentifier()),
            ],
        ];
    }

    public function toData(): UpdateProfileData
    {
        return UpdateProfileData::fromArray($this->validated());
    }
}
