import { usePage } from '@inertiajs/react';

import type { ClientPermission } from '@/types';

/** Returns a check for the signed-in staff member's permissions (super admins have them all). */
export function useStaffCan(): (permission: string) => boolean {
    const staff = usePage().props.auth.staff;

    return (permission) => !!staff?.permissions.includes(permission);
}

/**
 * Returns a check for the signed-in client user's permissions (the owner has
 * them all). Hides controls only; the server enforces every permission.
 */
export function useClientCan(): (permission: ClientPermission) => boolean {
    const user = usePage().props.auth.user;

    return (permission) => !!user?.permissions.includes(permission);
}
