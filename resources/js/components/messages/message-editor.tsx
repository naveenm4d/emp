import { Popover } from '@base-ui/react/popover';
import type { LucideIcon } from 'lucide-react';
import {
    Bold,
    Braces,
    Code,
    Italic,
    List,
    ListOrdered,
    Quote,
    RotateCcw,
    Search,
    Smile,
    Strikethrough,
} from 'lucide-react';
import { useLayoutEffect, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** What `{{ … }}` placeholders stand for (see RsvpMessage on the server). */
export const MESSAGE_PLACEHOLDER_LABELS: Record<string, string> = {
    'guest.name': 'Guest name',
    'event.title': 'Event title',
    'event.date': 'Date',
    'event.time': 'Time',
    'event.venue': 'Venue',
    'rsvp.link': 'RSVP link',
};

const EMOJI: { name: string; items: string[] }[] = [
    {
        name: 'Celebrate',
        items: [
            '🎉',
            '🎊',
            '🥳',
            '🎈',
            '🎂',
            '🍰',
            '🎁',
            '🎀',
            '🪅',
            '🎆',
            '🎇',
            '✨',
            '🌟',
            '⭐',
            '💫',
            '🔥',
            '🥂',
            '🍾',
            '🎶',
            '🎵',
            '💃',
            '🕺',
            '📸',
            '🎤',
        ],
    },
    {
        name: 'Love',
        items: [
            '❤️',
            '🧡',
            '💛',
            '💚',
            '💙',
            '💜',
            '🤍',
            '🩷',
            '💕',
            '💞',
            '💓',
            '💗',
            '💖',
            '💘',
            '💝',
            '💍',
            '💐',
            '🌹',
            '🌸',
            '🌺',
            '🌷',
            '🌼',
            '💒',
            '👰',
        ],
    },
    {
        name: 'Smileys',
        items: [
            '😀',
            '😃',
            '😄',
            '😁',
            '😊',
            '🙂',
            '😉',
            '😍',
            '🥰',
            '😘',
            '🤗',
            '🤩',
            '😇',
            '🥹',
            '😂',
            '🤭',
            '😎',
            '🤔',
            '🙏',
            '👋',
            '👏',
            '🙌',
            '👍',
            '🤝',
        ],
    },
    {
        name: 'Food',
        items: [
            '🍽️',
            '🍛',
            '🍚',
            '🥘',
            '🍲',
            '🍜',
            '🍕',
            '🍔',
            '🥗',
            '🍤',
            '🍗',
            '🥭',
            '🍍',
            '🥥',
            '☕',
            '🍵',
            '🧃',
            '🍹',
            '🍸',
            '🍷',
            '🧁',
            '🍩',
            '🍪',
            '🍫',
        ],
    },
    {
        name: 'Places',
        items: [
            '📍',
            '🗺️',
            '🏛️',
            '🏨',
            '🏡',
            '⛪',
            '🕌',
            '🛕',
            '🌴',
            '🏖️',
            '🌅',
            '🌄',
            '🚗',
            '🚌',
            '✈️',
            '🚆',
            '🅿️',
            '🕒',
            '📅',
            '🗓️',
            '⏰',
            '📞',
            '✉️',
            '🔗',
        ],
    },
];

type Format = {
    label: string;
    icon: LucideIcon;
    shortcut?: string;
    apply: (editor: Selection) => Edit;
};

type Selection = { value: string; start: number; end: number };
type Edit = { value: string; start: number; end: number };

/** Wraps the selection in `mark` (or inserts `mark…mark` around a word placeholder). */
function wrap(mark: string, placeholder: string) {
    return ({ value, start, end }: Selection): Edit => {
        const selected = value.slice(start, end) || placeholder;
        const next = `${value.slice(0, start)}${mark}${selected}${mark}${value.slice(end)}`;

        return {
            value: next,
            start: start + mark.length,
            end: start + mark.length + selected.length,
        };
    };
}

/** Puts `prefix` (or a numbered prefix) at the start of every selected line. */
function prefixLines(prefix: (index: number) => string) {
    return ({ value, start, end }: Selection): Edit => {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = value.indexOf('\n', end);
        const stop = lineEnd === -1 ? value.length : lineEnd;
        const block = value
            .slice(lineStart, stop)
            .split('\n')
            .map((line, index) => `${prefix(index)}${line}`)
            .join('\n');

        return {
            value: `${value.slice(0, lineStart)}${block}${value.slice(stop)}`,
            start: lineStart,
            end: lineStart + block.length,
        };
    };
}

const FORMATS: Format[] = [
    { label: 'Bold', icon: Bold, shortcut: 'b', apply: wrap('*', 'bold') },
    {
        label: 'Italic',
        icon: Italic,
        shortcut: 'i',
        apply: wrap('_', 'italic'),
    },
    { label: 'Strikethrough', icon: Strikethrough, apply: wrap('~', 'text') },
    { label: 'Monospace', icon: Code, apply: wrap('```', 'text') },
    { label: 'Bulleted list', icon: List, apply: prefixLines(() => '- ') },
    {
        label: 'Numbered list',
        icon: ListOrdered,
        apply: prefixLines((index) => `${index + 1}. `),
    },
    { label: 'Quote', icon: Quote, apply: prefixLines(() => '> ') },
];

type MessageEditorProps = {
    id: string;
    value: string;
    onChange: (value: string) => void;
    /** Shown when empty: what's sent instead (the default text). */
    placeholder?: string;
    maxLength?: number;
    invalid?: boolean;
    /** Label of the "reset" action; shown while there is custom text. */
    resetLabel?: string;
    className?: string;
};

/**
 * A message editor for guest messages: a toolbar that writes WhatsApp's own
 * formatting (bold, italic, strike, monospace, lists, quotes), an emoji
 * picker, and an "Insert" menu for placeholders like {{ guest.name }}, over
 * a growing text box with a character count.
 */
export function MessageEditor({
    id,
    value,
    onChange,
    placeholder,
    maxLength = 1000,
    invalid = false,
    resetLabel = 'Use the default',
    className,
}: MessageEditorProps) {
    const area = useRef<HTMLTextAreaElement>(null);

    // Grow with the text.
    useLayoutEffect(() => {
        const element = area.current;

        if (element) {
            element.style.height = 'auto';
            element.style.height = `${Math.max(element.scrollHeight, 112)}px`;
        }
    }, [value]);

    /** Applies an edit to the current selection and keeps the cursor on it. */
    const edit = (change: (selection: Selection) => Edit) => {
        const element = area.current;
        const selection = {
            value,
            start: element?.selectionStart ?? value.length,
            end: element?.selectionEnd ?? value.length,
        };
        const next = change(selection);

        onChange(next.value.slice(0, maxLength));
        requestAnimationFrame(() => {
            element?.focus();
            element?.setSelectionRange(next.start, next.end);
        });
    };

    const insert = (text: string) =>
        edit(({ value: current, start, end }) => ({
            value: `${current.slice(0, start)}${text}${current.slice(end)}`,
            start: start + text.length,
            end: start + text.length,
        }));

    const onKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (!(e.metaKey || e.ctrlKey)) {
            return;
        }

        const format = FORMATS.find(
            (item) => item.shortcut === e.key.toLowerCase(),
        );

        if (format) {
            e.preventDefault();
            edit(format.apply);
        }
    };

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border bg-card transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50',
                invalid ? 'border-destructive' : 'border-input',
                className,
            )}
        >
            <div
                role="toolbar"
                aria-label="Formatting"
                className="flex items-center gap-0.5 border-b border-border bg-raised/60 px-1.5 py-1"
            >
                {/* On phones the styles scroll sideways; emoji and Insert stay in view. */}
                <div className="flex min-w-0 flex-1 scrollbar-none items-center gap-0.5 overflow-x-auto">
                    {FORMATS.map((format, index) => (
                        <span key={format.label} className="contents">
                            {index === 4 && <Divider />}
                            <ToolButton
                                label={
                                    format.shortcut
                                        ? `${format.label} (⌘${format.shortcut.toUpperCase()})`
                                        : format.label
                                }
                                onClick={() => edit(format.apply)}
                            >
                                <format.icon className="size-4" />
                            </ToolButton>
                        </span>
                    ))}
                </div>
                <Divider />
                <EmojiPicker onPick={insert} />
                <PlaceholderMenu onPick={(key) => insert(`{{ ${key} }}`)} />
            </div>

            <textarea
                ref={area}
                id={id}
                value={value}
                maxLength={maxLength}
                aria-invalid={invalid || undefined}
                placeholder={placeholder}
                onChange={(e) => onChange(e.target.value)}
                onKeyDown={onKeyDown}
                className="block w-full resize-none bg-transparent px-3.5 py-3 text-base leading-relaxed outline-none placeholder:text-muted-foreground md:text-sm"
            />

            <div className="flex items-center justify-between gap-3 border-t border-border px-3.5 py-1.5 text-[11px] text-muted-foreground">
                <span className="hidden min-w-0 truncate sm:block">
                    {value
                        ? '*bold* _italic_ ~strike~ work on WhatsApp'
                        : 'Empty: guests get the text shown'}
                </span>
                <span className="ml-auto flex shrink-0 items-center gap-3">
                    {value && (
                        <button
                            type="button"
                            onClick={() => onChange('')}
                            className="inline-flex items-center gap-1 font-semibold text-link hover:underline"
                        >
                            <RotateCcw className="size-3" /> {resetLabel}
                        </button>
                    )}
                    <span
                        className={cn(
                            'tabular-nums',
                            value.length > maxLength * 0.9 && 'text-warning',
                        )}
                    >
                        {value.length}/{maxLength}
                    </span>
                </span>
            </div>
        </div>
    );
}

function Divider() {
    return <span aria-hidden className="mx-1 h-5 w-px shrink-0 bg-border" />;
}

function ToolButton({
    label,
    onClick,
    children,
}: {
    label: string;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            // Keep the text box's selection when clicking the toolbar.
            onMouseDown={(e) => e.preventDefault()}
            onClick={onClick}
            className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-card hover:text-foreground"
        >
            {children}
        </button>
    );
}

/** A grid of emoji by category, with a search over the category names. */
function EmojiPicker({ onPick }: { onPick: (emoji: string) => void }) {
    const [open, setOpen] = useState(false);
    const [category, setCategory] = useState(EMOJI[0].name);
    const [query, setQuery] = useState('');
    const shown = query
        ? EMOJI.filter((group) =>
              group.name.toLowerCase().includes(query.toLowerCase()),
          ).flatMap((group) => group.items)
        : (EMOJI.find((group) => group.name === category)?.items ?? []);

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger
                aria-label="Emoji"
                title="Emoji"
                onMouseDown={(e) => e.preventDefault()}
                className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-card hover:text-foreground data-popup-open:bg-card data-popup-open:text-foreground"
            >
                <Smile className="size-4" />
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Positioner
                    side="bottom"
                    align="start"
                    sideOffset={6}
                    className="z-50"
                >
                    <Popover.Popup className="w-72 rounded-2xl bg-popover p-2.5 text-popover-foreground shadow-xl ring-1 ring-border outline-none motion-safe:animate-fade-in">
                        <label className="relative mb-2 flex items-center">
                            <Search className="pointer-events-none absolute left-2.5 size-3.5 text-muted-foreground" />
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search: love, food, places…"
                                className="h-8 w-full rounded-lg bg-raised pr-2 pl-8 text-sm outline-none"
                            />
                        </label>
                        {!query && (
                            <div className="mb-2 flex [scrollbar-width:none] gap-1 overflow-x-auto">
                                {EMOJI.map((group) => (
                                    <button
                                        key={group.name}
                                        type="button"
                                        onClick={() => setCategory(group.name)}
                                        className={cn(
                                            'h-7 shrink-0 rounded-full px-2.5 text-xs font-semibold',
                                            category === group.name
                                                ? 'bg-strong text-strong-foreground'
                                                : 'text-muted-foreground hover:bg-raised',
                                        )}
                                    >
                                        {group.name}
                                    </button>
                                ))}
                            </div>
                        )}
                        <div className="grid max-h-48 grid-cols-8 gap-0.5 overflow-y-auto">
                            {shown.map((emoji, index) => (
                                <button
                                    key={`${emoji}-${index}`}
                                    type="button"
                                    onClick={() => onPick(emoji)}
                                    className="flex aspect-square items-center justify-center rounded-lg text-xl transition-transform hover:scale-110 hover:bg-raised"
                                >
                                    {emoji}
                                </button>
                            ))}
                            {shown.length === 0 && (
                                <p className="col-span-8 py-4 text-center text-xs text-muted-foreground">
                                    No matching category
                                </p>
                            )}
                        </div>
                    </Popover.Popup>
                </Popover.Positioner>
            </Popover.Portal>
        </Popover.Root>
    );
}

/** "Insert ▾": the placeholders filled in for each guest. */
function PlaceholderMenu({ onPick }: { onPick: (key: string) => void }) {
    const [open, setOpen] = useState(false);

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger
                onMouseDown={(e) => e.preventDefault()}
                className="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-muted-foreground transition-colors hover:bg-card hover:text-foreground data-popup-open:bg-card data-popup-open:text-foreground"
            >
                <Braces className="size-3.5" /> Insert
            </Popover.Trigger>
            <Popover.Portal>
                <Popover.Positioner
                    side="bottom"
                    align="end"
                    sideOffset={6}
                    className="z-50"
                >
                    <Popover.Popup className="w-56 rounded-2xl bg-popover p-1.5 text-popover-foreground shadow-xl ring-1 ring-border outline-none motion-safe:animate-fade-in">
                        <p className="px-2.5 pt-1.5 pb-1 text-[11px] font-semibold text-muted-foreground">
                            Filled in for each guest
                        </p>
                        {Object.entries(MESSAGE_PLACEHOLDER_LABELS).map(
                            ([key, label]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => {
                                        onPick(key);
                                        setOpen(false);
                                    }}
                                    className="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-raised"
                                >
                                    <span className="font-semibold">
                                        {label}
                                    </span>
                                    <code className="text-[10px] text-muted-foreground">
                                        {`{{ ${key} }}`}
                                    </code>
                                </button>
                            ),
                        )}
                    </Popover.Popup>
                </Popover.Positioner>
            </Popover.Portal>
        </Popover.Root>
    );
}
