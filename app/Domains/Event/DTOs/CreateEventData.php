<?php

namespace App\Domains\Event\DTOs;

use App\Core\DTOs\DataTransferObject;

final readonly class CreateEventData extends DataTransferObject
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $template_id,
        public ?string $description = null,
        public int $max_capacity = 0,
        public string $registration_type = 'guest_list_only',
        public ?string $event_type = null,
        public ?string $location_name = null,
        public ?string $location_address = null,
        public ?string $map_url = null,
        public ?string $event_date = null,
        public ?string $start_time = null,
        public ?string $end_time = null,
        public ?string $invitation_message = null,
        public ?string $reminder_message = null,
        public bool $auto_reminders = false,
        public int $remind_after_days = 3,
        public int $remind_before_days = 2,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            title: trim($data['title']),
            slug: $data['slug'],
            template_id: $data['template_id'],
            description: $data['description'] ?? null,
            max_capacity: (int) ($data['max_capacity'] ?? 0),
            registration_type: $data['registration_type'] ?? 'guest_list_only',
            event_type: $data['event_type'] ?? null,
            location_name: $data['location_name'] ?? null,
            location_address: $data['location_address'] ?? null,
            map_url: $data['map_url'] ?? null,
            event_date: $data['event_date'] ?? null,
            start_time: $data['start_time'] ?? null,
            end_time: $data['end_time'] ?? null,
            invitation_message: $data['invitation_message'] ?? null,
            reminder_message: $data['reminder_message'] ?? null,
            auto_reminders: (bool) ($data['auto_reminders'] ?? false),
            remind_after_days: (int) ($data['remind_after_days'] ?? 3),
            remind_before_days: (int) ($data['remind_before_days'] ?? 2),
        );
    }
}
