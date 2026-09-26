<?php

namespace App\Domains\Guest\Http\Requests;

/**
 * Validation rules shared by every guest form (dashboard + public).
 */
final class GuestRules
{
    /** Every guest needs a way to be reached. */
    public const string CONTACT_REQUIRED = 'Add a phone number or an email.';

    /** @return array<string, list<string>> how many plus-ones and children the client invites the guest with */
    public static function invitedParty(): array
    {
        return [
            'invited_additional_guests' => ['nullable', 'integer', 'min:0', 'max:20'],
            'invited_children' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function contact(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s\-().]{6,}$/'],
        ];
    }
}
