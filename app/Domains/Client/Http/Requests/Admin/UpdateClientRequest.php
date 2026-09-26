<?php

namespace App\Domains\Client\Http\Requests\Admin;

use App\Domains\Client\DTOs\UpdateProfileData;
use App\Domains\Client\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Staff editing a client's account; the password is only changed when given. */
class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:clients.update` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(Client::class)->ignore($client->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function toData(): UpdateProfileData
    {
        return UpdateProfileData::fromArray($this->safe()->only(['name', 'email']));
    }

    public function password(): ?string
    {
        return $this->filled('password') ? (string) $this->validated('password') : null;
    }
}
