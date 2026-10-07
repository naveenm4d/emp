<?php

namespace App\Domains\Event\DTOs;

use App\Core\DTOs\PartialData;

final class UpdateEventData extends PartialData
{
    protected static function fields(): array
    {
        return [
            'title', 'slug', 'template_id', 'description', 'max_capacity', 'registration_type',
            'event_type', 'location_name', 'location_address', 'map_url',
            'event_date', 'start_time', 'end_time', 'invitation_message',
            'reminder_message', 'invitation_subject', 'reminder_subject', 'auto_reminders', 'remind_after_days', 'remind_before_days',
        ];
    }

    protected function normalize(array $values): array
    {
        if (array_key_exists('max_capacity', $values)) {
            $values['max_capacity'] = (int) ($values['max_capacity'] ?? 0);
        }

        if (array_key_exists('auto_reminders', $values)) {
            $values['auto_reminders'] = (bool) $values['auto_reminders'];
        }

        foreach (['remind_after_days', 'remind_before_days'] as $days) {
            if (array_key_exists($days, $values)) {
                $values[$days] = (int) $values[$days];
            }
        }

        return $values;
    }
}
