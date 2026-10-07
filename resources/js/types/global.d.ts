import type {
    Client,
    ClientPlanUsage,
    ClientUser,
    Event,
    StaffMember,
} from '@/types/models';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            area: 'web' | 'client' | 'admin';
            auth: {
                client?:
                    | (Omit<Client, 'plan'> & { plan: ClientPlanUsage })
                    | null;
                /** Who is signed in to the client dashboard. */
                user?: ClientUser | null;
                staff?: StaffMember | null;
            };
            flash: { success: string | null; error: string | null };
            /** Client's upcoming events for the event switcher; only after a partial reload asks for it. */
            eventSwitcher?: Event[];
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
