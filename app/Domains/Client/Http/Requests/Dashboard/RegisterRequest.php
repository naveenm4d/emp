<?php

namespace App\Domains\Client\Http\Requests\Dashboard;

use App\Domains\Client\DTOs\RegisterClientData;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.ClientUser::class, 'unique:'.Client::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function toData(): RegisterClientData
    {
        return RegisterClientData::fromArray($this->validated());
    }
}
