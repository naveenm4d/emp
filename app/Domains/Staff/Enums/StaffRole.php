<?php

namespace App\Domains\Staff\Enums;

use App\Core\Enums\Concerns\HasValues;

enum StaffRole: string
{
    use HasValues;

    /** Bypasses every permission check. */
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Viewer = 'viewer';

    /**
     * Permissions granted by default when a staff member is created
     * with this role and no explicit permissions.
     *
     * @return list<StaffPermission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => StaffPermission::cases(),
            self::Admin => [
                StaffPermission::StaffRead,
                StaffPermission::ClientsRead,
                StaffPermission::ClientsUpdate,
                StaffPermission::EventsRead,
                StaffPermission::EventsCreate,
                StaffPermission::EventsUpdate,
                StaffPermission::EventsDelete,
                StaffPermission::TemplatesRead,
                StaffPermission::TemplatesManage,
                StaffPermission::StatsRead,
                StaffPermission::NotificationsRead,
                StaffPermission::NotificationsRetry,
            ],
            self::Viewer => [
                StaffPermission::ClientsRead,
                StaffPermission::EventsRead,
                StaffPermission::TemplatesRead,
                StaffPermission::StatsRead,
            ],
        };
    }
}
