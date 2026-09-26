import { router } from '@inertiajs/react';
import {
    Ban,
    BellRing,
    Copy,
    MoreHorizontal,
    RotateCw,
    Send,
} from 'lucide-react';

import { rsvpDisplayStatus } from '@/components/rsvps/rsvp-status';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { expire, remind, resend, send } from '@/routes/client/rsvps';
import type { Rsvp } from '@/types';

/** Row actions on the RSVPs tab: Send or Remind as the button; resend, copy and expire in a ⋯ menu. */
export function RsvpActions({ rsvp }: { rsvp: Rsvp }) {
    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });
    const status = rsvpDisplayStatus(rsvp);
    const noPhone = !rsvp.guest?.phone;

    if (status !== 'pending' && status !== 'sent') {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-1">
            {status === 'pending' ? (
                <Button
                    size="sm"
                    variant="outline"
                    disabled={noPhone}
                    onClick={() => post(send.url(rsvp))}
                >
                    <Send /> Send
                </Button>
            ) : (
                <Button
                    size="sm"
                    variant="outline"
                    title="Remind the guest to reply"
                    disabled={noPhone}
                    onClick={() => post(remind.url(rsvp))}
                >
                    <BellRing /> Remind
                </Button>
            )}
            <DropdownMenu>
                <DropdownMenuTrigger
                    aria-label={`More actions for ${rsvp.guest?.name ?? 'this RSVP link'}`}
                    className="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none data-popup-open:bg-muted"
                >
                    <MoreHorizontal className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent>
                    {status === 'sent' && (
                        <DropdownMenuItem
                            disabled={noPhone}
                            onClick={() => post(resend.url(rsvp))}
                        >
                            <RotateCw /> Resend invitation
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuItem
                        onClick={() =>
                            navigator.clipboard.writeText(rsvp.rsvp_url)
                        }
                    >
                        <Copy /> Copy RSVP link
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onClick={() => post(expire.url(rsvp))}
                    >
                        <Ban /> Expire link
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
