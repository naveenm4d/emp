import type { Client, StaffMember } from '@/types/models';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            area: 'web' | 'client' | 'admin';
            auth: { client?: Client | null; staff?: StaffMember | null };
            flash: { success: string | null; error: string | null };
            [key: string]: unknown;
        };
    }
}

declare global {
    interface Window {
        /** Set while an invitation is shown; template JS finds its markup here. */
        empInvitation?: { root: ShadowRoot };
    }
}
