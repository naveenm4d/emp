<?php

namespace App\Domains\Guest\Http\Requests;

/**
 * Validation rules shared by every guest form (dashboard + public).
 */
final class GuestRules
{
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
