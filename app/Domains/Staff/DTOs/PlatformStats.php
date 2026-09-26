<?php

namespace App\Domains\Staff\DTOs;

use App\Core\DTOs\DataTransferObject;

final readonly class PlatformStats extends DataTransferObject
{
    /** @param array<string, int> $eventsByState */
    public function __construct(
        public int $clients,
        public int $events,
        public array $eventsByState,
        public int $guests,
        public int $rsvps,
        public int $notifications,
        public int $failedNotifications,
    ) {}
}
