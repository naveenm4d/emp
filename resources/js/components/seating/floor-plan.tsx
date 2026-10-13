import type { LucideIcon } from 'lucide-react';
import {
    Bath,
    Cake,
    Camera,
    ClipboardList,
    DoorOpen,
    Heart,
    LandPlot,
    Maximize,
    Mic2,
    Minus,
    Music,
    Pencil,
    Plus,
    RotateCcw,
    RotateCw,
    Shapes,
    Sparkles,
    Trash2,
    Utensils,
    Wine,
} from 'lucide-react';
import type { PointerEvent as ReactPointerEvent, ReactNode } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

import type {
    ElementBox,
    Plan,
    Point,
} from '@/components/seating/floor-plan-layout';
import {
    addVenueElement,
    CANVAS,
    clamp,
    contentBounds,
    initialLayout,
    removeVenueElement,
    renameVenueElement,
    saveFloorPlan,
    ROTATE_STEP,
    snap,
    turnElement,
} from '@/components/seating/floor-plan-layout';
import { TableToken, tableTokenSize } from '@/components/seating/table-token';
import { SlideToConfirm } from '@/components/shared/confirm-bar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type {
    Event,
    EventTable,
    VenueElement,
    VenueElementKind,
    VenueElementKindOption,
} from '@/types';

const MIN_SIZE = 40;
const MAX_SIZE = 800;
const MIN_ZOOM = 0.2;
const MAX_ZOOM = 1.5;

export const venueElementIcons: Record<VenueElementKind, LucideIcon> = {
    stage: Mic2,
    poruwa: Heart,
    dance_floor: Sparkles,
    buffet: Utensils,
    bar: Wine,
    cake_table: Cake,
    dj: Music,
    photo_booth: Camera,
    entrance: DoorOpen,
    registration: ClipboardList,
    restrooms: Bath,
    custom: Shapes,
};

/** The part of the canvas on screen: zoom, and where the canvas origin sits (screen px). */
type View = { scale: number; x: number; y: number };

/** Keeps the canvas covering the viewport (or centred when it's smaller). */
function clampView(view: View, viewport: { width: number; height: number }) {
    const fit = (offset: number, size: number, screen: number) =>
        size * view.scale <= screen
            ? (screen - size * view.scale) / 2
            : clamp(offset, screen - size * view.scale, 0);

    return {
        scale: view.scale,
        x: fit(view.x, CANVAS.width, viewport.width),
        y: fit(view.y, CANVAS.height, viewport.height),
    };
}

/** What a pointer drag moves: the whole canvas, an item, or an element's size. */
type Drag = {
    kind: 'pan' | 'table' | 'element' | 'resize';
    id: string;
    pointer: Point;
    /** Tables and the canvas have no rotation (0). */
    start: ElementBox;
    moved: boolean;
};

/**
 * The Map view of the Seating tab: a large room where tables and venue
 * elements (stage, poruwa, buffet, …) are dragged into place. Drag the empty
 * floor to move around; zoom with the buttons (or pinch / ctrl + scroll).
 * Positions are saved when a drag ends. Tapping a table opens its seats.
 */
export function FloorPlan({
    event,
    tables,
    elements,
    kinds,
    editable,
    onOpenTable,
    className,
}: {
    event: Pick<Event, 'id'>;
    tables: EventTable[];
    elements: VenueElement[];
    kinds: VenueElementKindOption[];
    editable: boolean;
    onOpenTable: (table: EventTable) => void;
    className?: string;
}) {
    const viewport = useRef<HTMLDivElement>(null);
    const size = useRef({ width: 0, height: 0 });
    const [view, setViewState] = useState<View>({ scale: 0.5, x: 0, y: 0 });
    const viewRef = useRef(view);
    const [plan, setPlanState] = useState(() =>
        initialLayout(tables, elements),
    );
    // The latest layout, for saving when a drag ends.
    const planRef = useRef(plan);
    const [selected, setSelectedState] = useState<string | null>(null);
    // The selected element asks "Remove?" in its toolbar before it goes.
    const [confirmingRemove, setConfirmingRemove] = useState(false);
    const setSelected = (id: string | null) => {
        setSelectedState(id);
        setConfirmingRemove(false);
    };
    const drag = useRef<Drag | null>(null);

    const setView = (next: View) => {
        const clamped = clampView(next, size.current);
        viewRef.current = clamped;
        setViewState(clamped);
    };

    const setPlan = (next: Plan) => {
        planRef.current = next;
        setPlanState(next);
    };

    /** Zooms to show everything on the plan (or the top-left of the room). */
    const fit = () => {
        const { width, height } = size.current;
        const bounds = contentBounds(planRef.current, tables) ?? {
            x: 0,
            y: 0,
            width: 1600,
            height: 1000,
        };
        const padding = 120;
        const scale = clamp(
            Math.min(
                width / (bounds.width + padding * 2),
                height / (bounds.height + padding * 2),
            ),
            MIN_ZOOM,
            1,
        );

        setView({
            scale,
            x: width / 2 - (bounds.x + bounds.width / 2) * scale,
            y: height / 2 - (bounds.y + bounds.height / 2) * scale,
        });
    };

    /** Zooms around a point of the viewport (its centre by default). */
    const zoom = (factor: number, around?: Point) => {
        const current = viewRef.current;
        const scale = clamp(current.scale * factor, MIN_ZOOM, MAX_ZOOM);
        const focus = around ?? {
            x: size.current.width / 2,
            y: size.current.height / 2,
        };

        setView({
            scale,
            x: focus.x - ((focus.x - current.x) / current.scale) * scale,
            y: focus.y - ((focus.y - current.y) / current.scale) * scale,
        });
    };

    // Server data wins after every save or change elsewhere.
    useEffect(() => {
        const next = initialLayout(tables, elements);
        planRef.current = next;
        setPlanState(next);
    }, [tables, elements]);

    // Track the viewport size; fit the plan the first time it's measured.
    useLayoutEffect(() => {
        const element = viewport.current;

        if (!element) {
            return;
        }

        let first = true;
        const observer = new ResizeObserver(([entry]) => {
            size.current = {
                width: entry.contentRect.width,
                height: entry.contentRect.height,
            };

            if (first) {
                first = false;
                fit();
            } else {
                setView(viewRef.current);
            }
        });
        observer.observe(element);

        return () => observer.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Pinch on a trackpad (ctrl + wheel) zooms; plain scrolling scrolls the page.
    useEffect(() => {
        const element = viewport.current;

        if (!element) {
            return;
        }

        const onWheel = (e: WheelEvent) => {
            if (!e.ctrlKey && !e.metaKey) {
                return;
            }

            e.preventDefault();
            const rect = element.getBoundingClientRect();
            zoom(Math.exp(-e.deltaY / 200), {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top,
            });
        };

        element.addEventListener('wheel', onWheel, { passive: false });

        return () => element.removeEventListener('wheel', onWheel);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const begin = (
        e: ReactPointerEvent,
        kind: Drag['kind'],
        id: string,
        start: ElementBox,
    ) => {
        e.stopPropagation();
        e.currentTarget.setPointerCapture(e.pointerId);
        drag.current = {
            kind,
            id,
            pointer: { x: e.clientX, y: e.clientY },
            start,
            moved: false,
        };
    };

    const move = (e: ReactPointerEvent) => {
        const current = drag.current;

        if (!current) {
            return;
        }

        const screenDx = e.clientX - current.pointer.x;
        const screenDy = e.clientY - current.pointer.y;

        if (!current.moved && Math.hypot(screenDx, screenDy) < 4) {
            return;
        }

        const { start } = current;

        if (current.kind === 'pan') {
            current.moved = true;
            setView({
                ...viewRef.current,
                x: start.x + screenDx,
                y: start.y + screenDy,
            });

            return;
        }

        if (!editable) {
            return;
        }

        current.moved = true;
        const dx = screenDx / viewRef.current.scale;
        const dy = screenDy / viewRef.current.scale;
        const previous = planRef.current;

        if (current.kind === 'resize') {
            // The handle turns with the element: measure the drag along its sides.
            const angle = (start.rotation * Math.PI) / 180;
            const along = dx * Math.cos(angle) + dy * Math.sin(angle);
            const across = -dx * Math.sin(angle) + dy * Math.cos(angle);
            setPlan({
                ...previous,
                elements: {
                    ...previous.elements,
                    [current.id]: {
                        ...start,
                        width: clamp(
                            snap(start.width + along),
                            MIN_SIZE,
                            Math.min(MAX_SIZE, CANVAS.width - start.x),
                        ),
                        height: clamp(
                            snap(start.height + across),
                            MIN_SIZE,
                            Math.min(MAX_SIZE, CANVAS.height - start.y),
                        ),
                    },
                },
            });

            return;
        }

        const x = clamp(snap(start.x + dx), 0, CANVAS.width - start.width);
        const y = clamp(snap(start.y + dy), 0, CANVAS.height - start.height);

        setPlan(
            current.kind === 'table'
                ? {
                      ...previous,
                      tables: { ...previous.tables, [current.id]: { x, y } },
                  }
                : {
                      ...previous,
                      elements: {
                          ...previous.elements,
                          [current.id]: { ...start, x, y },
                      },
                  },
        );
    };

    const end = (onClick: () => void) => () => {
        const current = drag.current;
        drag.current = null;

        if (!current) {
            return;
        }

        if (!current.moved) {
            onClick();
        } else if (current.kind !== 'pan') {
            saveFloorPlan(event, planRef.current);
        }
    };

    /** Places a new venue element in the middle of what's on screen. */
    const addElement = (kind: VenueElementKindOption) => {
        const { scale, x, y } = viewRef.current;

        addVenueElement(event, kind, {
            x: (size.current.width / 2 - x) / scale,
            y: (size.current.height / 2 - y) / scale,
        });
    };

    const removeElement = (element: VenueElement) => {
        setSelected(null);
        removeVenueElement(element);
    };

    const rotateElement = (id: string, step: number) => {
        const previous = planRef.current;
        const next = {
            ...previous,
            elements: {
                ...previous.elements,
                [id]: turnElement(previous.elements[id], step),
            },
        };
        setPlan(next);
        saveFloorPlan(event, next);
    };

    return (
        <div
            ref={viewport}
            className={cn(
                'relative h-[65svh] min-h-96 cursor-grab touch-none overflow-hidden bg-raised select-none active:cursor-grabbing',
                className,
            )}
            onPointerDown={(e) =>
                begin(e, 'pan', 'canvas', {
                    ...viewRef.current,
                    width: 0,
                    height: 0,
                    rotation: 0,
                })
            }
            onPointerMove={move}
            onPointerUp={end(() => setSelected(null))}
        >
            <div
                className="absolute top-0 left-0 origin-top-left bg-card bg-[radial-gradient(var(--input)_1.5px,transparent_1.5px)] bg-size-[40px_40px] shadow-card"
                style={{
                    width: CANVAS.width,
                    height: CANVAS.height,
                    transform: `translate(${view.x}px, ${view.y}px) scale(${view.scale})`,
                }}
            >
                {elements.map((element) => {
                    const box = plan.elements[element.id] ?? element;
                    const Icon = venueElementIcons[element.kind];
                    const isSelected = selected === element.id;
                    const angle = (box.rotation * Math.PI) / 180;
                    // The turned element's upright bounding box, where its toolbar sits.
                    const upright = {
                        width:
                            Math.abs(box.width * Math.cos(angle)) +
                            Math.abs(box.height * Math.sin(angle)),
                        height:
                            Math.abs(box.width * Math.sin(angle)) +
                            Math.abs(box.height * Math.cos(angle)),
                    };

                    return (
                        <div
                            key={element.id}
                            className={cn(
                                'absolute flex flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed bg-raised text-center text-muted-foreground',
                                isSelected
                                    ? 'border-primary text-foreground'
                                    : 'border-input',
                                editable &&
                                    'cursor-grab active:cursor-grabbing',
                            )}
                            style={{
                                left: box.x,
                                top: box.y,
                                width: box.width,
                                height: box.height,
                                rotate: `${box.rotation}deg`,
                            }}
                            onPointerDown={(e) =>
                                editable
                                    ? begin(e, 'element', element.id, box)
                                    : undefined
                            }
                            onPointerMove={move}
                            onPointerUp={end(() => setSelected(element.id))}
                        >
                            {/* Upside down past a quarter turn, so the name stays readable. */}
                            <div
                                className="flex max-w-full flex-col items-center gap-1.5"
                                style={
                                    box.rotation > 90 && box.rotation < 270
                                        ? { rotate: '180deg' }
                                        : undefined
                                }
                            >
                                <Icon className="size-8" strokeWidth={1.75} />
                                <span className="max-w-full truncate px-3 text-[20px] font-semibold">
                                    {element.display_label}
                                </span>
                            </div>

                            {editable && isSelected && (
                                <>
                                    {/* Turned back upright over the element, so the toolbar reads level. */}
                                    <div
                                        className="pointer-events-none absolute top-1/2 left-1/2"
                                        style={{
                                            ...upright,
                                            translate: '-50% -50%',
                                            rotate: `${-box.rotation}deg`,
                                        }}
                                    >
                                        <div
                                            className={cn(
                                                'pointer-events-auto',
                                                'absolute left-1/2 flex -translate-x-1/2 shadow-card',
                                                confirmingRemove
                                                    ? '-top-3 w-72 origin-bottom -translate-y-full flex-col rounded-2xl bg-card p-3.5 text-foreground shadow-lg ring-1 ring-border'
                                                    : '-top-16 items-center gap-2 rounded-full bg-strong p-2 whitespace-nowrap text-strong-foreground',
                                            )}
                                            // The question stays screen-sized whatever the zoom.
                                            style={
                                                confirmingRemove
                                                    ? {
                                                          scale: String(
                                                              1 / view.scale,
                                                          ),
                                                      }
                                                    : undefined
                                            }
                                            onPointerDown={(e) =>
                                                e.stopPropagation()
                                            }
                                            onPointerUp={(e) =>
                                                e.stopPropagation()
                                            }
                                        >
                                            {confirmingRemove ? (
                                                <div
                                                    role="alertdialog"
                                                    aria-label={`Remove ${element.display_label}?`}
                                                    className="grid gap-3 motion-safe:animate-fade-in"
                                                >
                                                    <p className="text-sm leading-snug font-semibold">
                                                        Remove{' '}
                                                        {element.display_label}{' '}
                                                        from the floor plan?
                                                    </p>
                                                    <SlideToConfirm
                                                        label="Remove"
                                                        onCancel={() =>
                                                            setConfirmingRemove(
                                                                false,
                                                            )
                                                        }
                                                        onConfirm={() =>
                                                            removeElement(
                                                                element,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            ) : (
                                                <span className="flex gap-2 motion-safe:animate-fade-in">
                                                    <button
                                                        type="button"
                                                        aria-label={`Rename ${element.display_label}`}
                                                        onClick={() =>
                                                            renameVenueElement(
                                                                element,
                                                                box,
                                                            )
                                                        }
                                                        className="flex size-10 items-center justify-center rounded-full hover:bg-current/15"
                                                    >
                                                        <Pencil className="size-5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        aria-label={`Turn ${element.display_label} left`}
                                                        title="Turn left"
                                                        onClick={() =>
                                                            rotateElement(
                                                                element.id,
                                                                -ROTATE_STEP,
                                                            )
                                                        }
                                                        className="flex size-10 items-center justify-center rounded-full hover:bg-current/15"
                                                    >
                                                        <RotateCcw className="size-5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        aria-label={`Turn ${element.display_label} right`}
                                                        title="Turn right"
                                                        onClick={() =>
                                                            rotateElement(
                                                                element.id,
                                                                ROTATE_STEP,
                                                            )
                                                        }
                                                        className="flex size-10 items-center justify-center rounded-full hover:bg-current/15"
                                                    >
                                                        <RotateCw className="size-5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        aria-label={`Remove ${element.display_label}`}
                                                        onClick={() =>
                                                            setConfirmingRemove(
                                                                true,
                                                            )
                                                        }
                                                        className="flex size-10 items-center justify-center rounded-full hover:bg-current/15"
                                                    >
                                                        <Trash2 className="size-5" />
                                                    </button>
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <span
                                        aria-label="Resize"
                                        className="absolute -right-3 -bottom-3 size-8 cursor-nwse-resize rounded-full border-4 border-card bg-primary"
                                        onPointerDown={(e) =>
                                            begin(e, 'resize', element.id, box)
                                        }
                                        onPointerMove={move}
                                        onPointerUp={end(() => {})}
                                    />
                                </>
                            )}
                        </div>
                    );
                })}

                {tables.map((table) => {
                    const point = plan.tables[table.id];

                    return (
                        <button
                            key={table.id}
                            type="button"
                            className={cn(
                                'absolute rounded-full outline-none focus-visible:ring-8 focus-visible:ring-ring/50',
                                editable &&
                                    'cursor-grab active:cursor-grabbing',
                            )}
                            style={{ left: point.x, top: point.y }}
                            onPointerDown={(e) =>
                                begin(e, 'table', table.id, {
                                    ...point,
                                    ...tableTokenSize(table.shape),
                                    rotation: 0,
                                })
                            }
                            onPointerMove={move}
                            onPointerUp={end(() => onOpenTable(table))}
                            onKeyDown={(e) =>
                                (e.key === 'Enter' || e.key === ' ') &&
                                onOpenTable(table)
                            }
                        >
                            <TableToken table={table} />
                        </button>
                    );
                })}
            </div>

            {/* Tools float over the map; pointer events stop here so they don't pan. */}
            {editable && (
                <div
                    className="absolute top-3 left-3"
                    onPointerDown={(e) => e.stopPropagation()}
                    onPointerUp={(e) => e.stopPropagation()}
                >
                    <DropdownMenu>
                        {/* Icon only; the label slides out on hover or keyboard focus. */}
                        <DropdownMenuTrigger
                            aria-label="Add venue element"
                            className="group inline-flex h-10 items-center rounded-full bg-card px-3 text-sm font-semibold whitespace-nowrap shadow-card outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                        >
                            <LandPlot className="size-4 shrink-0" />
                            <span className="max-w-0 overflow-hidden opacity-0 transition-all duration-200 group-hover:ml-1.5 group-hover:max-w-40 group-hover:opacity-100 group-focus-visible:ml-1.5 group-focus-visible:max-w-40 group-focus-visible:opacity-100">
                                Add venue element
                            </span>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            {kinds.map((kind) => {
                                const Icon = venueElementIcons[kind.value];

                                return (
                                    <DropdownMenuItem
                                        key={kind.value}
                                        onClick={() => addElement(kind)}
                                    >
                                        <Icon /> {kind.label}
                                    </DropdownMenuItem>
                                );
                            })}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            )}
            <div
                className="absolute right-3 bottom-3 flex flex-col overflow-hidden rounded-xl bg-card shadow-card"
                onPointerDown={(e) => e.stopPropagation()}
                onPointerUp={(e) => e.stopPropagation()}
            >
                <MapButton label="Zoom in" onClick={() => zoom(1.25)}>
                    <Plus className="size-4" />
                </MapButton>
                <MapButton label="Zoom out" onClick={() => zoom(0.8)}>
                    <Minus className="size-4" />
                </MapButton>
                <MapButton label="Show everything" onClick={fit}>
                    <Maximize className="size-4" />
                </MapButton>
            </div>
        </div>
    );
}

function MapButton({
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
            onClick={onClick}
            className="flex size-10 items-center justify-center border-b border-border last:border-0 hover:bg-raised"
        >
            {children}
        </button>
    );
}
