import { router } from '@inertiajs/react';

import { tableTokenSize } from '@/components/seating/table-token';
import { layout as saveLayout } from '@/routes/client/events/seating';
import { store as storeElement } from '@/routes/client/events/venue-elements';
import {
    destroy as destroyElement,
    update as updateElement,
} from '@/routes/client/venue-elements';
import type {
    Event,
    EventTable,
    VenueElement,
    VenueElementKindOption,
    VenueSetting,
} from '@/types';

/**
 * What the 2D and 3D floor plans share: the canvas, where everything stands,
 * and the requests that save it.
 */

/** Canvas size in canvas units (matches FloorPlanData::WIDTH / HEIGHT). */
export const CANVAS = { width: 4000, height: 2400 };
export const GRID = 20;

export type Point = { x: number; y: number };
export type Box = Point & { width: number; height: number };
/** A venue element's box, turned `rotation` degrees clockwise round its centre. */
export type ElementBox = Box & { rotation: number };
export type Plan = {
    tables: Record<string, Point>;
    elements: Record<string, ElementBox>;
};

/** Degrees an element turns per press of a rotate button. */
export const ROTATE_STEP = 15;

/** Turns by `step` degrees, kept in 0–359. */
export const turn = (rotation: number, step: number) =>
    (((rotation + step) % 360) + 360) % 360;

/**
 * Turns an element by `step` degrees round its centre, nudging it so the
 * turned outline stays on the canvas.
 */
export function turnElement(box: ElementBox, step: number): ElementBox {
    const rotation = turn(box.rotation, step);
    const angle = (rotation * Math.PI) / 180;
    const halfWidth =
        (Math.abs(box.width * Math.cos(angle)) +
            Math.abs(box.height * Math.sin(angle))) /
        2;
    const halfHeight =
        (Math.abs(box.width * Math.sin(angle)) +
            Math.abs(box.height * Math.cos(angle))) /
        2;
    const centreX = clamp(
        box.x + box.width / 2,
        halfWidth,
        CANVAS.width - halfWidth,
    );
    const centreY = clamp(
        box.y + box.height / 2,
        halfHeight,
        CANVAS.height - halfHeight,
    );

    return {
        ...box,
        rotation,
        x: clamp(
            Math.round(centreX - box.width / 2),
            0,
            CANVAS.width - box.width,
        ),
        y: clamp(
            Math.round(centreY - box.height / 2),
            0,
            CANVAS.height - box.height,
        ),
    };
}

export const snap = (value: number) => Math.round(value / GRID) * GRID;
export const clamp = (value: number, min: number, max: number) =>
    Math.min(Math.max(value, min), max);

/** Tables not placed yet go in rows below the top of the room, in order. */
export function initialLayout(
    tables: EventTable[],
    elements: VenueElement[],
): Plan {
    const placed: Record<string, Point> = {};
    let slot = 0;

    for (const table of tables) {
        if (table.x !== null && table.y !== null) {
            placed[table.id] = { x: table.x, y: table.y };
            continue;
        }

        const perRow = 7;
        placed[table.id] = {
            x: 80 + (slot % perRow) * 210,
            y: Math.min(
                260 + Math.floor(slot / perRow) * 210,
                CANVAS.height - 160,
            ),
        };
        slot += 1;
    }

    return {
        tables: placed,
        elements: Object.fromEntries(
            elements.map((element) => [
                element.id,
                {
                    x: element.x,
                    y: element.y,
                    width: element.width,
                    height: element.height,
                    rotation: element.rotation,
                },
            ]),
        ),
    };
}

/** Everything on the plan, as one box (null when it's empty). */
export function contentBounds(plan: Plan, tables: EventTable[]): Box | null {
    const boxes: Box[] = [
        ...tables
            .filter((table) => plan.tables[table.id])
            .map((table) => ({
                ...plan.tables[table.id],
                ...tableTokenSize(table.shape),
            })),
        ...Object.values(plan.elements),
    ];

    if (boxes.length === 0) {
        return null;
    }

    const left = Math.min(...boxes.map((box) => box.x));
    const top = Math.min(...boxes.map((box) => box.y));

    return {
        x: left,
        y: top,
        width: Math.max(...boxes.map((box) => box.x + box.width)) - left,
        height: Math.max(...boxes.map((box) => box.y + box.height)) - top,
    };
}

/** Saves where everything stands (and, when given, the venue setting). */
export function saveFloorPlan(
    event: Pick<Event, 'id'>,
    plan: Plan,
    venue?: VenueSetting,
) {
    router.patch(
        saveLayout.url(event),
        {
            tables: Object.entries(plan.tables).map(([id, point]) => ({
                id,
                ...point,
            })),
            elements: Object.entries(plan.elements).map(([id, box]) => ({
                id,
                ...box,
            })),
            ...(venue ? { venue } : {}),
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['tables', 'venueElements', 'venueSetting', 'flash'],
        },
    );
}

/** Adds a venue element centred on a canvas point (custom ones are named first). */
export function addVenueElement(
    event: Pick<Event, 'id'>,
    kind: VenueElementKindOption,
    centre: Point,
) {
    const label = kind.value === 'custom' ? prompt('Name this element') : null;

    if (kind.value === 'custom' && !label?.trim()) {
        return;
    }

    router.post(
        storeElement.url(event),
        {
            kind: kind.value,
            label: label?.trim() ?? null,
            x: clamp(
                snap(centre.x - kind.width / 2),
                0,
                CANVAS.width - kind.width,
            ),
            y: clamp(
                snap(centre.y - kind.height / 2),
                0,
                CANVAS.height - kind.height,
            ),
            width: kind.width,
            height: kind.height,
        },
        { preserveScroll: true },
    );
}

export function renameVenueElement(element: VenueElement, box: Box) {
    const label = prompt('Name', element.label ?? element.display_label);

    if (label === null) {
        return;
    }

    router.patch(
        updateElement.url(element.id),
        { kind: element.kind, label: label.trim() || null, ...box },
        { preserveScroll: true },
    );
}

export function removeVenueElement(element: VenueElement) {
    router.delete(destroyElement.url(element.id), { preserveScroll: true });
}
