import { CheckCheck, Mail, MessageCircle, Smartphone } from 'lucide-react';
import { useState } from 'react';

import { SegmentedControl } from '@/components/ui/segmented-control';
import { renderWhatsApp, toPlainText } from '@/lib/message-format';
import { cn } from '@/lib/utils';

export type PreviewChannel = 'whatsapp' | 'email' | 'sms';

const CHANNELS: {
    value: PreviewChannel;
    label: string;
    icon: typeof Mail;
}[] = [
    { value: 'whatsapp', label: 'WhatsApp', icon: MessageCircle },
    { value: 'email', label: 'Email', icon: Mail },
    { value: 'sms', label: 'SMS', icon: Smartphone },
];

/**
 * How a guest message looks on each channel: a WhatsApp bubble with its
 * formatting, an email (with the subject), or a plain SMS. `subject` only
 * applies to email; leave it out to hide the email tab.
 */
export function MessagePreview({
    message,
    subject,
    guestName,
    from,
    className,
}: {
    /** The message with placeholders already filled in. */
    message: string;
    /** The email subject, filled in; undefined = no email tab. */
    subject?: string;
    guestName: string;
    /** Sender line of the email preview (the event's title). */
    from: string;
    className?: string;
}) {
    const channels = CHANNELS.filter(
        (channel) => channel.value !== 'email' || subject !== undefined,
    );
    const [channel, setChannel] = useState<PreviewChannel>('whatsapp');
    const shown = channels.some((item) => item.value === channel)
        ? channel
        : 'whatsapp';

    return (
        <div className={cn('grid gap-2.5', className)}>
            <div className="grid gap-2 sm:flex sm:items-center sm:justify-between">
                <span className="text-xs font-semibold text-muted-foreground">
                    Preview for {guestName}
                </span>
                <SegmentedControl
                    className="w-full sm:w-auto"
                    label="Preview channel"
                    value={shown}
                    onChange={setChannel}
                    items={channels.map((item) => ({
                        value: item.value,
                        label: item.label,
                    }))}
                />
            </div>

            {shown === 'whatsapp' && (
                <div className="rounded-2xl bg-[#e7ddd3] bg-[radial-gradient(rgba(0,0,0,0.05)_1px,transparent_1px)] bg-size-[14px_14px] p-3 sm:p-4 dark:bg-[#0b141a] dark:bg-[radial-gradient(rgba(255,255,255,0.04)_1px,transparent_1px)]">
                    <div className="relative ml-auto w-fit max-w-[95%] rounded-2xl rounded-tr-sm bg-[#d9fdd3] px-3 pt-2 pb-5 text-[14px] leading-snug text-[#111b21] shadow-sm sm:max-w-[92%] dark:bg-[#005c4b] dark:text-[#e9edef]">
                        <div className="wrap-break-word">
                            {renderWhatsApp(message)}
                        </div>
                        <span className="absolute right-2 bottom-1 flex items-center gap-1 text-[10px] text-black/45 dark:text-white/60">
                            9:41
                            <CheckCheck className="size-3.5 text-sky-500" />
                        </span>
                    </div>
                </div>
            )}

            {shown === 'email' && (
                <div className="overflow-hidden rounded-2xl border border-border bg-card">
                    <div className="grid gap-1 border-b border-border bg-raised/60 px-4 py-3 text-xs">
                        <div className="flex gap-2">
                            <span className="w-14 shrink-0 text-muted-foreground">
                                From
                            </span>
                            <span className="truncate font-semibold">
                                {from}
                            </span>
                        </div>
                        <div className="flex gap-2">
                            <span className="w-14 shrink-0 text-muted-foreground">
                                To
                            </span>
                            <span className="truncate">{guestName}</span>
                        </div>
                        <div className="flex gap-2">
                            <span className="w-14 shrink-0 text-muted-foreground">
                                Subject
                            </span>
                            <span className="truncate font-bold">
                                {subject}
                            </span>
                        </div>
                    </div>
                    <div className="px-4 py-4 text-sm leading-relaxed wrap-break-word">
                        {renderWhatsApp(message)}
                    </div>
                </div>
            )}

            {shown === 'sms' && (
                <div className="rounded-2xl bg-raised p-4">
                    <div className="w-fit max-w-[92%] rounded-2xl rounded-tl-sm bg-card px-3 py-2 text-sm leading-snug wrap-break-word whitespace-pre-line shadow-sm">
                        {toPlainText(message)}
                    </div>
                    <p className="mt-2 text-[11px] text-muted-foreground">
                        SMS has no formatting; the marks are removed.
                    </p>
                </div>
            )}
        </div>
    );
}
