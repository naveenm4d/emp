<?php

namespace App\Domains\Client\Http\Requests\Dashboard;

use App\Domains\Client\DTOs\InviteMemberData;
use App\Domains\Client\Models\ClientUser;

/** Inviting someone to sign in to the account. Each email can belong to one account only. */
class InviteMemberRequest extends MemberAccessRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('create', ClientUser::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.ClientUser::class],
            ...parent::rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'email.unique' => 'This email already signs in to '.config('app.name').'. Use another email.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function toData(): InviteMemberData
    {
        return InviteMemberData::fromArray($this->validated());
    }
}
