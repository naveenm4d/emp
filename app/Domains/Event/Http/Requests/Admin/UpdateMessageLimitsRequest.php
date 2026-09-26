<?php

namespace App\Domains\Event\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff: how many invitations / reminders each guest of the event can get.
 * Empty means the platform default.
 */
class UpdateMessageLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:events.update` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'max_invitations_per_guest' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_reminders_per_guest' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'max_invitations_per_guest' => 'invitations per guest',
            'max_reminders_per_guest' => 'reminders per guest',
        ];
    }

    public function invitations(): ?int
    {
        $value = $this->validated('max_invitations_per_guest');

        return $value === null ? null : (int) $value;
    }

    public function reminders(): ?int
    {
        $value = $this->validated('max_reminders_per_guest');

        return $value === null ? null : (int) $value;
    }
}
