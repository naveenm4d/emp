<?php

namespace App\Domains\Guest\DTOs;

use App\Core\DTOs\PartialData;

final class UpdateGuestData extends PartialData
{
    protected static function fields(): array
    {
        return [
            'name', 'email', 'phone', 'notes', 'invitation_message', 'reminder_message',
            'invited_additional_guests', 'invited_children',
        ];
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

        foreach (['invited_additional_guests', 'invited_children'] as $count) {
            if (array_key_exists($count, $values)) {
                $values[$count] = $values[$count] === null ? null : (int) $values[$count];
            }
        }

        return $values;
    }
}
