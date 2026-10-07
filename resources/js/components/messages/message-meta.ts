import type { LucideIcon } from 'lucide-react';
import {
    AlertTriangle,
    Check,
    CheckCheck,
    Clock,
    Eye,
    Mail,
    MessageCircle,
    Smartphone,
} from 'lucide-react';

import type { Notification, NotificationStatus } from '@/types';

/** How each status reads and looks. */
export const statusStyles: Record<
    NotificationStatus,
    { label: string; icon: LucideIcon; tone: string }
> = {
    pending: {
        label: 'Queued',
        icon: Clock,
        tone: 'bg-foreground/6 text-muted-foreground',
    },
    sent: { label: 'Sent', icon: Check, tone: 'bg-info-muted text-info' },
    delivered: {
        label: 'Delivered',
        icon: CheckCheck,
        tone: 'bg-success-muted text-success',
    },
    read: {
        label: 'Read',
        icon: Eye,
        tone: 'bg-accent text-accent-foreground',
    },
    failed: {
        label: 'Failed',
        icon: AlertTriangle,
        tone: 'bg-destructive-muted text-destructive',
    },
};

export const channelIcons: Record<Notification['channel'], LucideIcon> = {
    whatsapp: MessageCircle,
    email: Mail,
    sms: Smartphone,
};
