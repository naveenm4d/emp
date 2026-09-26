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
    case ClientsUpdate = 'clients.update';
    /** Change a client's plan, expiry and event credits, and events' extra guests. */
    case ClientsPlan = 'clients.plan';
    case EventsRead = 'events.read';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    case TemplatesRead = 'templates.read';
    case TemplatesManage = 'templates.manage';
    case StatsRead = 'stats.read';
    case NotificationsRead = 'notifications.read';
    case NotificationsRetry = 'notifications.retry';
    /** See the log of everything staff did in the admin console. */
    case ActivityRead = 'activity.read';
}
