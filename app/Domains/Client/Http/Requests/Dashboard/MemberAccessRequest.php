<?php

namespace App\Domains\Client\Http\Requests\Dashboard;

use App\Domains\Client\DTOs\MemberAccessData;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A team member's permissions and the events they see (only the account's own events). */
abstract class MemberAccessRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ClientUser $user */
        $user = $this->user('client');

        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::enum(ClientPermission::class)],
            'all_events' => ['required', 'boolean'],
            'event_ids' => ['array'],
            'event_ids.*' => [
                'uuid', 'distinct',
                Rule::exists(Event::class, 'id')->where('client_id', $user->client_id)->whereNull('deleted_at'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['event_ids.*.exists' => 'Choose events from this account.'];
    }

    public function access(): MemberAccessData
    {
        return MemberAccessData::fromArray($this->validated());
    }
}
