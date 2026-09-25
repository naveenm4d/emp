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
        public bool $require_approval = false,
        public ?string $event_type = null,
        public ?string $location_name = null,
        public ?string $location_address = null,
        public ?string $map_url = null,
        public ?string $event_date = null,
        public ?string $start_time = null,
        public ?string $end_time = null,
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
            require_approval: (bool) ($data['require_approval'] ?? false),
            event_type: $data['event_type'] ?? null,
            location_name: $data['location_name'] ?? null,
            location_address: $data['location_address'] ?? null,
            map_url: $data['map_url'] ?? null,
            event_date: $data['event_date'] ?? null,
            start_time: $data['start_time'] ?? null,
            end_time: $data['end_time'] ?? null,
        );
    }
}
