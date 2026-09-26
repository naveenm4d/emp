import { usePage } from '@inertiajs/react';

/** Returns a check for the signed-in staff member's permissions (super admins have them all). */
export function useStaffCan(): (permission: string) => boolean {
    const staff = usePage().props.auth.staff;

    return (permission) => !!staff?.permissions.includes(permission);
}
