<?php

namespace App\Domains\Client\Http\Requests\Admin;

use App\Domains\Client\DTOs\CreateClientData;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Staff creating a client account; without a password the owner gets an invitation. */
class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:clients.create` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.ClientUser::class, 'unique:'.Client::class],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function toData(): CreateClientData
    {
        return CreateClientData::fromArray($this->validated());
    }
}
