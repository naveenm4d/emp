import { FormField } from '@/components/shared/form-field';
import { MessagePlaceholders } from '@/components/shared/message-placeholders';
import { Textarea } from '@/components/ui/textarea';

/** Fills a message's placeholders like the server does; appends the link when the text has none. */
export function previewMessage(
    text: string,
    values: Record<string, string>,
): string {
    let hasLink = false;
    const message = text.replace(
        /\{\{\s*([a-z.]+)\s*\}\}/g,
        (match, key: string) => {
            if (!(key in values)) {
                return match;
            }

            hasLink ||= key === 'rsvp.link';

            return values[key];
        },
    );

    return hasLink ? message : `${message.trimEnd()}\n\n${values['rsvp.link']}`;
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
};

/** A WhatsApp message textarea with copyable placeholder chips and a live preview. */
export function MessageField({
    id,
    label,
    hint,
    value,
    onChange,
    error,
    defaultMessage,
    sample,
}: MessageFieldProps) {
    return (
        <div className="space-y-3">
            <FormField label={label} htmlFor={id} error={error} hint={hint}>
                <Textarea
                    id={id}
                    rows={3}
                    value={value}
                    aria-invalid={!!error}
                    placeholder={defaultMessage}
                    onChange={(e) => onChange(e.target.value)}
                />
            </FormField>
            <MessagePlaceholders message={value} />
            <div className="rounded-lg bg-muted/50 p-3 text-sm whitespace-pre-line">
                <p className="mb-1 text-xs font-medium text-muted-foreground">
                    Preview for a guest named {sample['guest.name']}
                </p>
                {previewMessage(value || defaultMessage, sample)}
            </div>
        </div>
    );
}
