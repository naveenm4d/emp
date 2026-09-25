import type { Client, StaffMember } from '@/types/models';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            area: 'site' | 'client' | 'internal';
            auth: { client?: Client | null; staff?: StaffMember | null };
            flash: { success: string | null; error: string | null };
            [key: string]: unknown;
        };
    }
}
