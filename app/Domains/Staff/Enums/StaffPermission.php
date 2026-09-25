<?php

namespace App\Domains\Staff\Enums;

use App\Core\Enums\Concerns\HasValues;

/**
 * Fine-grained staff permissions. Each case is registered as a Gate
 * ability (see StaffServiceProvider) and used as `can:<value>` on routes.
 */
enum StaffPermission: string
{
    use HasValues;

    case StaffCreate = 'staff.create';
    case StaffRead = 'staff.read';
    case ClientsRead = 'clients.read';
    case EventsRead = 'events.read';
    case StatsRead = 'stats.read';
    case NotificationsRead = 'notifications.read';
    case NotificationsRetry = 'notifications.retry';
}
