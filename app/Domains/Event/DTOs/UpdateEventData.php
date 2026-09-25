<?php

namespace App\Domains\Event\DTOs;

use App\Core\DTOs\PartialData;

final class UpdateEventData extends PartialData
{
    protected static function fields(): array
    {
        return [
            'title', 'slug', 'template_id', 'description', 'max_capacity', 'require_approval',
            'event_type', 'location_name', 'location_address', 'map_url',
            'event_date', 'start_time', 'end_time',
        ];
    }

    protected function normalize(array $values): array
    {
        if (array_key_exists('max_capacity', $values)) {
            $values['max_capacity'] = (int) ($values['max_capacity'] ?? 0);
        }

        if (array_key_exists('require_approval', $values)) {
            $values['require_approval'] = (bool) $values['require_approval'];
        }

        return $values;
    }
}
