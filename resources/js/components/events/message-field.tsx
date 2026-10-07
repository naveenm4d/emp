import { Mail } from 'lucide-react';

import { MessageEditor } from '@/components/messages/message-editor';
import { MessagePreview } from '@/components/messages/message-preview';
import { MESSAGE_PLACEHOLDERS } from '@/components/shared/message-placeholders';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/** Fills a message's placeholders like the server does; appends the link when the text has none. */
export function previewMessage(
    text: string,
    values: Record<string, string>,
): string {
    let hasLink = false;
    const message = fillPlaceholders(text, values, (key) => {
        hasLink ||= key === 'rsvp.link';
    });

    return hasLink ? message : `${message.trimEnd()}\n\n${values['rsvp.link']}`;
}

/** Fills `{{ key }}` placeholders (subjects get no link appended). */
export function fillPlaceholders(
    text: string,
    values: Record<string, string>,
    onUsed?: (key: string) => void,
): string {
    return text.replace(/\{\{\s*([a-z.]+)\s*\}\}/g, (match, key: string) => {
        if (!(key in values)) {
            return match;
        }

        onUsed?.(key);

        return values[key];
    });
}

type MessageFieldProps = {
    id: string;
    label: string;
    hint: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    /** Sent when the field is left empty (shown as the placeholder and previewed). */
    defaultMessage: string;
    /** Sample values for the preview. */
    sample: Record<string, string>;
    /** The email subject; leave out where there's no email (e.g. a guest's own message). */
    subject?: {
        value: string;
        onChange: (value: string) => void;
        defaultSubject: string;
        error?: string;
    };
};

/**
 * A guest message (invitation or reminder): an optional email subject, the
 * styled editor, the placeholders it uses, and a live preview per channel.
 */
export function MessageField({
    id,
    label,
    hint,
    value,
    onChange,
    error,
    defaultMessage,
    sample,
    subject,
}: MessageFieldProps) {
    const used = new Set(
        [...(value || defaultMessage).matchAll(/\{\{\s*([a-z.]+)\s*\}\}/g)].map(
            (match) => match[1],
        ),
    );

    return (
        <div className="grid gap-4">
            <div>
                <h3 className="text-sm font-bold">{label}</h3>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>

            {subject && (
                <label className="grid gap-1.5" htmlFor={`${id}-subject`}>
                    <span className="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        Subject
                        <span className="inline-flex items-center gap-1 rounded-full bg-info-muted px-2 py-0.5 text-[10px] font-semibold text-info">
                            <Mail className="size-3" /> Email only
                            <span className="hidden sm:inline">
                                · not sent on WhatsApp or SMS
                            </span>
                        </span>
                    </span>
                    <Input
                        id={`${id}-subject`}
                        value={subject.value}
                        maxLength={150}
                        aria-invalid={!!subject.error}
                        placeholder={subject.defaultSubject}
                        onChange={(e) => subject.onChange(e.target.value)}
                    />
                    {subject.error && (
                        <span className="text-xs text-destructive">
                            {subject.error}
                        </span>
                    )}
                </label>
            )}

            <div className="grid gap-1.5">
                <MessageEditor
                    id={id}
                    value={value}
                    onChange={onChange}
                    placeholder={defaultMessage}
                    invalid={!!error}
                />
                {error && <p className="text-xs text-destructive">{error}</p>}
                <div className="flex flex-wrap gap-1.5">
                    {MESSAGE_PLACEHOLDERS.map((key) => (
                        <span
                            key={key}
                            className={cn(
                                'rounded-full px-2 py-0.5 font-mono text-[10px]',
                                used.has(key)
                                    ? 'bg-success-muted text-success'
                                    : 'bg-foreground/6 text-muted-foreground',
                            )}
                            title={
                                used.has(key)
                                    ? 'In the message'
                                    : key === 'rsvp.link'
                                      ? 'Added at the end when the message has none'
                                      : 'Not used'
                            }
                        >
                            {`{{ ${key} }}`}
                        </span>
                    ))}
                </div>
            </div>

            <MessagePreview
                message={previewMessage(value || defaultMessage, sample)}
                subject={
                    subject
                        ? fillPlaceholders(
                              subject.value || subject.defaultSubject,
                              sample,
                          )
                        : undefined
                }
                guestName={sample['guest.name']}
                from={`EMP · ${sample['event.title']}`}
            />
        </div>
    );
}
