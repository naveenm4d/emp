<?php

namespace App\Domains\Guest\DTOs;

use App\Core\DTOs\PartialData;

final class UpdateGuestData extends PartialData
{
    protected static function fields(): array
    {
        return ['name', 'email', 'phone', 'notes', 'invitation_message', 'reminder_message'];
    }

    protected function normalize(array $values): array
    {
        if (array_key_exists('name', $values)) {
            $values['name'] = trim((string) $values['name']);
        }

        if (array_key_exists('email', $values)) {
            $values['email'] = GuestContact::email($values['email']);
        }

        if (array_key_exists('phone', $values)) {
            $values['phone'] = GuestContact::phone($values['phone']);
        }

        return $values;
    }
}
