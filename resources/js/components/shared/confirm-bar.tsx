import { Popover } from '@base-ui/react/popover';
import { AlertTriangle, ChevronsRight, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

/** A critical action waiting for a second click. */
export type ConfirmRequest = {
    /** "Remove Nimali from the guest list?" */
    title: string;
    /** The button that does it: "Remove". */
    confirmLabel: string;
    onConfirm: () => void;
};

/** The pending confirmation of one area, and how to ask for or drop it. */
export function useConfirm() {
    const [request, setRequest] = useState<ConfirmRequest | null>(null);
    const ask = useCallback((next: ConfirmRequest) => setRequest(next), []);
    const cancel = useCallback(() => setRequest(null), []);

    return { request, ask, cancel };
}

/**
 * Where the card sits:
 *  - cover:  dims its `relative` parent and centres the card on it (a card, a group of buttons);
 *  - pop:    floats above its `relative` parent's right edge, outside any clipping (a table row's actions, an icon button);
 *  - inline: takes the place of what it replaces, in the normal flow.
 */
const variants = {
    cover: 'absolute inset-0 z-20 flex items-center justify-center rounded-[inherit] bg-background/70 p-3 backdrop-blur-[2px]',
    pop: '',
    inline: 'w-full max-w-sm',
};

/**
 * Inline delete confirmation ("confirm in place"): a small card with the
 * question and a slide-to-confirm track fades in right where the action was
 * clicked. Esc or ✕ keeps everything as it was.
 */
export function ConfirmBar({
    request,
    onCancel,
    variant = 'cover',
    className,
}: {
    request: ConfirmRequest | null;
    onCancel: () => void;
    variant?: keyof typeof variants;
    className?: string;
}) {
    const anchor = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        if (!request) {
            return;
        }

        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onCancel();
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [request, onCancel]);

    if (variant === 'pop') {
        return (
            <>
                <span
                    ref={anchor}
                    aria-hidden
                    className="pointer-events-none absolute inset-0"
                />
                <Popover.Root
                    open={request !== null}
                    onOpenChange={(open) => !open && onCancel()}
                >
                    <Popover.Portal>
                        <Popover.Positioner
                            anchor={anchor}
                            side="top"
                            align="end"
                            sideOffset={6}
                            className="z-50"
                        >
                            <Popover.Popup
                                className={cn(
                                    'w-72 outline-none motion-safe:animate-fade-in',
                                    className,
                                )}
                                // Portalled, but React events still bubble to the row.
                                onClick={(e) => e.stopPropagation()}
                                onPointerDown={(e) => e.stopPropagation()}
                            >
                                {request && (
                                    <ConfirmCard
                                        request={request}
                                        onCancel={onCancel}
                                    />
                                )}
                            </Popover.Popup>
                        </Popover.Positioner>
                    </Popover.Portal>
                </Popover.Root>
            </>
        );
    }

    if (!request) {
        return null;
    }

    return (
        <div
            className={cn(
                'motion-safe:animate-fade-in',
                variants[variant],
                className,
            )}
            onClick={(e) => e.stopPropagation()}
            onPointerDown={(e) => e.stopPropagation()}
        >
            <ConfirmCard request={request} onCancel={onCancel} />
        </div>
    );
}

/** The question over a slide-to-confirm track. */
function ConfirmCard({
    request,
    onCancel,
}: {
    request: ConfirmRequest;
    onCancel: () => void;
}) {
    return (
        <div
            role="alertdialog"
            aria-label={request.title}
            className="grid w-full max-w-xs gap-3 rounded-2xl bg-card p-3.5 text-left whitespace-normal shadow-lg ring-1 ring-border"
        >
            <div className="flex items-start gap-2.5">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-destructive-muted text-destructive">
                    <AlertTriangle className="size-4" strokeWidth={2} />
                </span>
                <p className="pt-1 text-sm leading-snug font-semibold">
                    {request.title}
                </p>
            </div>
            <SlideToConfirm
                label={request.confirmLabel}
                onCancel={onCancel}
                onConfirm={() => {
                    onCancel();
                    request.onConfirm();
                }}
            />
        </div>
    );
}

/**
 * "Slide to remove": drag the knob to the end of the track to confirm (like
 * the old "slide to answer"); letting go early springs it back. The knob also
 * confirms with Enter, and ✕ backs out.
 */
export function SlideToConfirm({
    label,
    onConfirm,
    onCancel,
    className,
}: {
    /** The action: "Remove" reads "Slide to remove". */
    label: string;
    onConfirm: () => void;
    onCancel: () => void;
    className?: string;
}) {
    const track = useRef<HTMLDivElement>(null);
    const drag = useRef<{ start: number; max: number } | null>(null);
    const [offset, setOffset] = useState(0);
    const [dragging, setDragging] = useState(false);
    const [done, setDone] = useState(false);
    const max = () => (track.current ? track.current.clientWidth - 48 : 0);
    const progress = max() > 0 ? offset / max() : 0;

    const finish = () => {
        setDone(true);
        setOffset(max());
        // Let the knob land before the action runs.
        setTimeout(onConfirm, 160);
    };

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <div
                ref={track}
                className="relative h-12 flex-1 touch-none overflow-hidden rounded-full bg-destructive-muted select-none"
            >
                <span
                    aria-hidden
                    className={cn(
                        'absolute inset-y-0 left-0 rounded-full bg-destructive/20',
                        !dragging && 'transition-[width] duration-300',
                    )}
                    style={{ width: offset + 48 }}
                />
                <span
                    aria-hidden
                    className="absolute inset-0 flex items-center justify-center pl-10 text-sm font-semibold text-destructive"
                    style={{ opacity: Math.max(0, 1 - progress * 1.6) }}
                >
                    <span className="motion-safe:animate-pulse">
                        Slide to {label.toLowerCase()} ›››
                    </span>
                </span>
                <button
                    type="button"
                    autoFocus
                    aria-label={`Slide to ${label.toLowerCase()} (or press Enter)`}
                    disabled={done}
                    className={cn(
                        'absolute top-1 left-1 flex size-10 cursor-grab items-center justify-center rounded-full bg-destructive text-white shadow-md outline-none focus-visible:ring-3 focus-visible:ring-destructive/40 active:cursor-grabbing',
                        !dragging && 'transition-transform duration-300',
                    )}
                    style={{ transform: `translateX(${offset}px)` }}
                    onPointerDown={(e) => {
                        e.stopPropagation();
                        e.currentTarget.setPointerCapture(e.pointerId);
                        drag.current = {
                            start: e.clientX - offset,
                            max: max(),
                        };
                        setDragging(true);
                    }}
                    onPointerMove={(e) => {
                        if (!drag.current) {
                            return;
                        }

                        setOffset(
                            Math.min(
                                Math.max(e.clientX - drag.current.start, 0),
                                drag.current.max,
                            ),
                        );
                    }}
                    onPointerUp={() => {
                        const current = drag.current;
                        drag.current = null;
                        setDragging(false);

                        if (current && offset >= current.max * 0.9) {
                            finish();
                        } else {
                            setOffset(0);
                        }
                    }}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            finish();
                        }
                    }}
                >
                    <ChevronsRight className="size-5" strokeWidth={2.25} />
                </button>
            </div>
            <button
                type="button"
                aria-label="Cancel"
                title="Cancel"
                onClick={onCancel}
                className="flex size-10 shrink-0 items-center justify-center rounded-full bg-foreground/6 text-muted-foreground hover:bg-foreground/10 hover:text-foreground"
            >
                <X className="size-4.5" />
            </button>
        </div>
    );
}
