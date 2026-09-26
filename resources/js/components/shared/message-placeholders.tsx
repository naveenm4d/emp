import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

/** Placeholders an invitation message can use (see RsvpMessage on the server). */
export const MESSAGE_PLACEHOLDERS = [
    'guest.name',
    'event.title',
    'event.date',
    'event.time',
    'event.venue',
    'rsvp.link',
];

/** Placeholders written correctly in the message ("{{guest.name}}" counts, like on the server). */
function usedPlaceholders(message: string): Set<string> {
    return new Set(
        [...message.matchAll(/\{\{\s*([a-z.]+)\s*\}\}/g)].map(
            (match) => match[1],
        ),
    );
}

/**
 * Placeholder chips that copy "{{ key }}" to the clipboard when clicked.
 * Chips already used in `message` are shown in green.
 */
export function MessagePlaceholders({
    message = '',
    className,
}: {
    message?: string;
    className?: string;
}) {
    const [copied, setCopied] = useState<string | null>(null);
    const used = usedPlaceholders(message);

    useEffect(() => {
        if (copied === null) {
            return;
        }

        const timer = setTimeout(() => setCopied(null), 1500);

        return () => clearTimeout(timer);
    }, [copied]);

    return (
        <div className={cn('space-y-1.5', className)}>
            <p className="text-xs text-muted-foreground">
                Click a placeholder to copy it; green ones are in the message.
                The RSVP link is added at the end when the message has no{' '}
                <code>{'{{ rsvp.link }}'}</code>.
            </p>
            <div className="flex flex-wrap gap-1.5">
                {MESSAGE_PLACEHOLDERS.map((key) => {
                    const placeholder = `{{ ${key} }}`;
                    const isCopied = copied === key;
                    const isUsed = used.has(key);

                    return (
                        <button
                            key={key}
                            type="button"
                            title={
                                isUsed
                                    ? `${placeholder} is in the message`
                                    : `Copy ${placeholder}`
                            }
                            onClick={() =>
                                navigator.clipboard
                                    .writeText(placeholder)
                                    .then(() => setCopied(key))
                            }
                            className={cn(
                                'inline-flex items-center gap-1 rounded-md border border-border bg-muted/50 px-2 py-0.5 font-mono text-xs transition-colors hover:bg-muted',
                                isUsed &&
                                    'border-emerald-500/50 bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/15 dark:text-emerald-400',
                            )}
                        >
                            {placeholder}
                            {isCopied || isUsed ? (
                                <Check className="size-3" />
                            ) : (
                                <Copy className="size-3 opacity-60" />
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
