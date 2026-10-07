import type { ReactNode } from 'react';

/**
 * Guest messages use WhatsApp's own formatting, so they look right where
 * they're sent: *bold*, _italic_, ~strike~, ```monospace```, "- " / "1. "
 * lists and "> " quotes. These helpers draw it (as React nodes, never raw
 * HTML) and strip it for SMS.
 */

const INLINE = /(\*[^*\n]+\*|_[^_\n]+_|~[^~\n]+~|```[^`]+```)/g;

function inline(text: string, key: string): ReactNode[] {
    return text.split(INLINE).map((part, index) => {
        const id = `${key}-${index}`;

        if (/^\*[^*\n]+\*$/.test(part)) {
            return <strong key={id}>{inline(part.slice(1, -1), id)}</strong>;
        }

        if (/^_[^_\n]+_$/.test(part)) {
            return <em key={id}>{inline(part.slice(1, -1), id)}</em>;
        }

        if (/^~[^~\n]+~$/.test(part)) {
            return <s key={id}>{inline(part.slice(1, -1), id)}</s>;
        }

        if (/^```[^`]+```$/.test(part)) {
            return (
                <code
                    key={id}
                    className="rounded bg-black/5 px-1 font-mono text-[0.9em] dark:bg-white/10"
                >
                    {part.slice(3, -3)}
                </code>
            );
        }

        return part;
    });
}

/** The message as WhatsApp shows it. */
export function renderWhatsApp(text: string): ReactNode {
    return text.split('\n').map((line, index) => {
        const key = `l${index}`;
        const bullet = /^\s*[-*•]\s+(.*)$/.exec(line);
        const numbered = /^\s*(\d+)\.\s+(.*)$/.exec(line);
        const quote = /^>\s?(.*)$/.exec(line);

        if (bullet) {
            return (
                <div key={key} className="flex gap-2">
                    <span aria-hidden>•</span>
                    <span>{inline(bullet[1], key)}</span>
                </div>
            );
        }

        if (numbered) {
            return (
                <div key={key} className="flex gap-2">
                    <span className="tabular-nums">{numbered[1]}.</span>
                    <span>{inline(numbered[2], key)}</span>
                </div>
            );
        }

        if (quote) {
            return (
                <div
                    key={key}
                    className="border-l-4 border-current/25 pl-2 opacity-80"
                >
                    {inline(quote[1], key)}
                </div>
            );
        }

        return (
            <div key={key} className="min-h-[1.25em]">
                {inline(line, key)}
            </div>
        );
    });
}

/** The message with its formatting marks removed (for SMS). */
export function toPlainText(text: string): string {
    return text
        .replace(/```([^`]+)```/g, '$1')
        .replace(/\*([^*\n]+)\*/g, '$1')
        .replace(/_([^_\n]+)_/g, '$1')
        .replace(/~([^~\n]+)~/g, '$1')
        .replace(/^>\s?/gm, '');
}
