<?php

namespace App\Domains\Staff\Services;

use App\Domains\Client\Contracts\ClientQueryServiceInterface;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Staff\Contracts\PlatformStatsServiceInterface;
use App\Domains\Staff\DTOs\PlatformStats;

/**
 * Aggregates platform-wide numbers through each domain's query service,
 * never by reading other domains' tables directly.
 */
class PlatformStatsService implements PlatformStatsServiceInterface
{
    public function __construct(
        private readonly ClientQueryServiceInterface $clients,
        private readonly EventQueryServiceInterface $events,
        private readonly GuestQueryServiceInterface $guests,
        private readonly RsvpQueryServiceInterface $rsvps,
        private readonly NotificationQueryServiceInterface $notifications,
    ) {}

    public function overview(): PlatformStats
    {
        return new PlatformStats(
            clients: $this->clients->count(),
            events: $this->events->count(),
            eventsByState: $this->events->countByState(),
            guests: $this->guests->count(),
            rsvps: $this->rsvps->count(),
            notifications: $this->notifications->count(),
            failedNotifications: $this->notifications->countFailed(),
        );
    }
}
