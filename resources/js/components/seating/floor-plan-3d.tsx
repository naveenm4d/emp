import {
    LandPlot,
    Maximize,
    Minus,
    Pencil,
    Plus,
    RotateCcw,
    RotateCw,
    SquareDashed,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

import { venueElementIcons } from '@/components/seating/floor-plan';
import type { Plan } from '@/components/seating/floor-plan-layout';
import {
    addVenueElement,
    initialLayout,
    removeVenueElement,
    ROTATE_STEP,
    renameVenueElement,
    saveFloorPlan,
    turnElement,
} from '@/components/seating/floor-plan-layout';
import type { SceneCallbacks } from '@/components/seating/scene/seating-scene';
import { SeatingScene } from '@/components/seating/scene/seating-scene';
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
    VenueElementKindOption,
    VenueSetting,
} from '@/types';

const VENUES: { value: VenueSetting; label: string }[] = [
    { value: 'indoor', label: 'Indoor' },
    { value: 'outdoor', label: 'Outdoor' },
];

/**
 * The 3D Map of the Seating tab: the floor plan as a hall (indoor) or a
 * garden (outdoor). Tables drop into place with their chairs, whose cushions
 * show each seat's RSVP status. Drag the floor to look around, drag a table
 * or element to move it (saved when it lands), tap a table to open its
 * seats, tap an element to rename or remove it. Calls `onUnavailable` when
 * the browser can't draw 3D, so the page can fall back to the 2D plan.
 */
export function FloorPlan3D({
    event,
    tables,
    elements,
    kinds,
    venue: savedVenue,
    editable,
    onOpenTable,
    onUnavailable,
    className,
}: {
    event: Pick<Event, 'id'>;
    tables: EventTable[];
    elements: VenueElement[];
    kinds: VenueElementKindOption[];
    venue: VenueSetting;
    editable: boolean;
    onOpenTable: (table: EventTable) => void;
    onUnavailable: () => void;
    className?: string;
}) {
    const container = useRef<HTMLDivElement>(null);
    const toolbar = useRef<HTMLDivElement>(null);
    const scene = useRef<SeatingScene | null>(null);
    const plan = useRef<Plan>(initialLayout(tables, elements));
    const [venue, setVenue] = useState(savedVenue);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [confirmingRemove, setConfirmingRemove] = useState(false);
    const selected = elements.find((element) => element.id === selectedId);

    // The latest props and handlers, for the scene's callbacks.
    const latest = useRef({ tables, onOpenTable });

    const callbacks: SceneCallbacks = {
        onOpenTable: (id) => {
            const table = latest.current.tables.find((t) => t.id === id);

            if (table) {
                latest.current.onOpenTable(table);
            }
        },
        onSelectElement: (id) => {
            setSelectedId(id);
            setConfirmingRemove(false);
        },
        onMove: (kind, id, point) => {
            const current = plan.current;
            plan.current =
                kind === 'table'
                    ? { ...current, tables: { ...current.tables, [id]: point } }
                    : {
                          ...current,
                          elements: {
                              ...current.elements,
                              [id]: { ...current.elements[id], ...point },
                          },
                      };
            saveFloorPlan(event, plan.current);
        },
        // Moved straight on the DOM: this runs every frame.
        onSelectionMove: (point) => {
            const element = toolbar.current;

            if (!element) {
                return;
            }

            element.style.visibility = point ? 'visible' : 'hidden';

            if (point) {
                element.style.transform = `translate(${point.x}px, ${point.y}px) translate(-50%, calc(-100% - 12px))`;
            }
        },
    };

    useLayoutEffect(() => {
        const element = container.current;

        if (!element) {
            return;
        }

        try {
            scene.current = new SeatingScene(
                element,
                callbacks,
                editable,
                window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            );
        } catch {
            onUnavailable();

            return;
        }

        return () => {
            scene.current?.dispose();
            scene.current = null;
        };
        // The scene lives as long as the map; props reach it below.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        latest.current = { tables, onOpenTable };
        scene.current?.setCallbacks(callbacks);
        scene.current?.setEditable(editable);
    });

    // Server data wins after every save or change elsewhere.
    useEffect(() => {
        plan.current = initialLayout(tables, elements);
        scene.current?.setData(tables, elements, plan.current);
    }, [tables, elements]);

    useEffect(() => setVenue(savedVenue), [savedVenue]);

    useEffect(() => {
        scene.current?.setVenue(venue);
    }, [venue]);

    const changeVenue = (next: VenueSetting) => {
        setVenue(next);

        if (editable) {
            saveFloorPlan(event, plan.current, next);
        }
    };

    const rotateElement = (id: string, step: number) => {
        const box = turnElement(plan.current.elements[id], step);
        plan.current = {
            ...plan.current,
            elements: { ...plan.current.elements, [id]: box },
        };
        scene.current?.rotateElement(id, box);
        saveFloorPlan(event, plan.current);
    };

    const addElement = (kind: VenueElementKindOption) => {
        const centre = scene.current?.centre();

        if (centre) {
            addVenueElement(event, kind, centre);
        }
    };

    return (
        <div
            className={cn(
                'relative h-[65svh] min-h-96 touch-none overflow-hidden bg-[#15100b] select-none',
                className,
            )}
        >
            <div ref={container} className="absolute inset-0" />

            {/* Tables for keyboard and screen reader users. */}
            <ul className="sr-only">
                {tables.map((table) => (
                    <li key={table.id}>
                        <button
                            type="button"
                            onClick={() => onOpenTable(table)}
                        >
                            Open {table.name}
                        </button>
                    </li>
                ))}
            </ul>

            {editable && selected && (
                <div
                    ref={toolbar}
                    className="invisible absolute top-0 left-0 z-10"
                    onPointerDown={(e) => e.stopPropagation()}
                >
                    {confirmingRemove ? (
                        <div
                            role="alertdialog"
                            aria-label={`Remove ${selected.display_label}?`}
                            className="grid w-72 gap-3 rounded-2xl bg-card p-3.5 text-foreground shadow-lg ring-1 ring-border motion-safe:animate-fade-in"
                        >
                            <p className="text-sm leading-snug font-semibold">
                                Remove {selected.display_label} from the floor
                                plan?
                            </p>
                            <SlideToConfirm
                                label="Remove"
                                onCancel={() => setConfirmingRemove(false)}
                                onConfirm={() => {
                                    scene.current?.select(null);
                                    removeVenueElement(selected);
                                }}
                            />
                        </div>
                    ) : (
                        <span className="flex gap-1 rounded-full bg-strong p-1.5 text-strong-foreground shadow-card motion-safe:animate-fade-in">
                            <button
                                type="button"
                                aria-label={`Rename ${selected.display_label}`}
                                title="Rename"
                                onClick={() =>
                                    renameVenueElement(selected, {
                                        ...selected,
                                        ...plan.current.elements[selected.id],
                                    })
                                }
                                className="flex size-9 items-center justify-center rounded-full hover:bg-current/15"
                            >
                                <Pencil className="size-4" />
                            </button>
                            <button
                                type="button"
                                aria-label={`Turn ${selected.display_label} left`}
                                title="Turn left"
                                onClick={() =>
                                    rotateElement(selected.id, -ROTATE_STEP)
                                }
                                className="flex size-9 items-center justify-center rounded-full hover:bg-current/15"
                            >
                                <RotateCcw className="size-4" />
                            </button>
                            <button
                                type="button"
                                aria-label={`Turn ${selected.display_label} right`}
                                title="Turn right"
                                onClick={() =>
                                    rotateElement(selected.id, ROTATE_STEP)
                                }
                                className="flex size-9 items-center justify-center rounded-full hover:bg-current/15"
                            >
                                <RotateCw className="size-4" />
                            </button>
                            <button
                                type="button"
                                aria-label={`Remove ${selected.display_label}`}
                                title="Remove"
                                onClick={() => setConfirmingRemove(true)}
                                className="flex size-9 items-center justify-center rounded-full hover:bg-current/15"
                            >
                                <Trash2 className="size-4" />
                            </button>
                        </span>
                    )}
                </div>
            )}

            {editable && (
                <div className="absolute top-3 left-3">
                    <DropdownMenu>
                        {/* Icon only; the label slides out on hover or keyboard focus. */}
                        <DropdownMenuTrigger
                            aria-label="Add venue element"
                            className="group inline-flex h-10 items-center rounded-full bg-card/90 px-3 text-sm font-semibold whitespace-nowrap shadow-card backdrop-blur outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
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
                role="radiogroup"
                aria-label="Venue"
                className="absolute top-3 right-3 flex rounded-full bg-black/45 p-1 text-[13px] font-semibold text-white shadow-card ring-1 ring-white/10 backdrop-blur"
            >
                {VENUES.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={venue === option.value}
                        onClick={() => changeVenue(option.value)}
                        className={cn(
                            'h-8 rounded-full px-3.5 transition-colors',
                            venue === option.value
                                ? 'bg-white text-neutral-900'
                                : 'text-white/80 hover:text-white',
                        )}
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            <div className="absolute right-3 bottom-3 flex flex-col overflow-hidden rounded-xl bg-card/90 shadow-card backdrop-blur">
                <MapButton
                    label="Zoom in"
                    onClick={() => scene.current?.zoom(1.3)}
                >
                    <Plus className="size-4" />
                </MapButton>
                <MapButton
                    label="Zoom out"
                    onClick={() => scene.current?.zoom(0.77)}
                >
                    <Minus className="size-4" />
                </MapButton>
                <MapButton
                    label="Show everything"
                    onClick={() => scene.current?.fit()}
                >
                    <Maximize className="size-4" />
                </MapButton>
                <MapButton
                    label="Top view"
                    onClick={() => scene.current?.toggleTopView()}
                >
                    <SquareDashed className="size-4" />
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
