import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import {
    CSS2DObject,
    CSS2DRenderer,
} from 'three/examples/jsm/renderers/CSS2DRenderer.js';

import type {
    ElementBox,
    Plan,
    Point,
} from '@/components/seating/floor-plan-layout';
import { CANVAS, clamp, snap } from '@/components/seating/floor-plan-layout';
import type {
    Built,
    RoomBounds,
    Surroundings,
    Ticker,
} from '@/components/seating/scene/models';
import {
    buildIndoor,
    buildOutdoor,
    buildTable,
    buildVenueElement,
    disposeObject,
    Kit,
} from '@/components/seating/scene/models';
import { tableTokenSize } from '@/components/seating/table-token';
import type { EventTable, VenueElement, VenueSetting } from '@/types';

/** Metres per canvas unit: a 150-unit round table is a 2.25 m circle of chairs. */
export const UNIT = 0.015;

const toWorld = (x: number, y: number) =>
    new THREE.Vector3(
        (x - CANVAS.width / 2) * UNIT,
        0,
        (y - CANVAS.height / 2) * UNIT,
    );

const toCanvas = (point: THREE.Vector3): Point => ({
    x: point.x / UNIT + CANVAS.width / 2,
    y: point.z / UNIT + CANVAS.height / 2,
});

const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);
const easeInOutCubic = (t: number) =>
    t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
const easeOutBack = (t: number) =>
    1 + 2.70158 * Math.pow(t - 1, 3) + 1.70158 * Math.pow(t - 1, 2);
const easeInBack = (t: number) => 2.70158 * t * t * t - 1.70158 * t * t;
const easeOutBounce = (t: number) => {
    const n = 7.5625;
    const d = 2.75;

    if (t < 1 / d) {
        return n * t * t;
    }

    if (t < 2 / d) {
        return n * (t -= 1.5 / d) * t + 0.75;
    }

    if (t < 2.5 / d) {
        return n * (t -= 2.25 / d) * t + 0.9375;
    }

    return n * (t -= 2.625 / d) * t + 0.984375;
};

type Tween = {
    start: number;
    duration: number;
    update: (progress: number) => void;
    done?: () => void;
    cancelled?: boolean;
};

type ItemKind = 'table' | 'element';

/** A table or venue element in the scene. */
type Item = {
    kind: ItemKind;
    id: string;
    signature: string;
    /** Footprint in canvas units (for converting a drag back to the plan). */
    size: { width: number; height: number };
    /** At the item's centre on the floor; glides to `target`. */
    root: THREE.Group;
    /** Lifts on hover and while dragging. */
    lift: THREE.Group;
    /** The model itself (scaled and dropped by the animations). */
    content: THREE.Group;
    hit: THREE.Mesh;
    halo: THREE.Mesh;
    label: CSS2DObject;
    ticks: Ticker[];
    target: THREE.Vector3;
    /** Turn round y, in radians (a plan rotation of θ° clockwise is -θ). */
    targetRotation: number;
    liftTarget: number;
    removing: boolean;
};

export type SceneCallbacks = {
    onOpenTable: (id: string) => void;
    onSelectElement: (id: string | null) => void;
    /** A drag ended: the item's new top-left corner, in canvas units. */
    onMove: (kind: ItemKind, id: string, point: Point) => void;
    /** Where the selected element's toolbar goes (px in the container), every frame. */
    onSelectionMove: (point: Point | null) => void;
};

/**
 * The 3D floor plan: tables, chairs and venue elements in a hall or a
 * garden. Orbit with a drag on the floor (pan with the right button or two
 * fingers, zoom with a pinch or ctrl + scroll); drag a table or element to
 * move it, tap a table to open it.
 */
export class SeatingScene {
    private readonly renderer: THREE.WebGLRenderer;
    private readonly labels: CSS2DRenderer;
    private readonly scene = new THREE.Scene();
    private readonly camera = new THREE.PerspectiveCamera(42, 1, 0.1, 400);
    private readonly controls: OrbitControls;
    private readonly kit = new Kit();
    private readonly timer = new THREE.Timer();
    private readonly raycaster = new THREE.Raycaster();
    private readonly floor = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
    private readonly grid: THREE.GridHelper;
    private readonly items = new Map<string, Item>();
    private readonly observers: { disconnect: () => void }[] = [];
    private readonly environmentMap: THREE.Texture;

    private tweens: Tween[] = [];
    private cameraTween: Tween | null = null;
    private surroundings: Surroundings | null = null;
    private venue: VenueSetting | null = null;
    private room: RoomBounds = { minX: -9, maxX: 9, minZ: -6, maxZ: 6 };
    private exposure = 1;
    private hovered: Item | null = null;
    private selected: Item | null = null;
    private press: {
        item: Item;
        pointerId: number;
        start: Point;
        offset: THREE.Vector3;
        moved: boolean;
    } | null = null;
    private emptyPress: Point | null = null;
    private firstData = true;

    constructor(
        private readonly container: HTMLElement,
        private callbacks: SceneCallbacks,
        private editable: boolean,
        private readonly reducedMotion: boolean,
    ) {
        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFShadowMap;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.domElement.className = 'block size-full outline-none';
        container.appendChild(this.renderer.domElement);

        this.labels = new CSS2DRenderer();
        Object.assign(this.labels.domElement.style, {
            position: 'absolute',
            inset: '0',
            pointerEvents: 'none',
        });
        container.appendChild(this.labels.domElement);

        // Soft reflections on the gold, silver and glass.
        const pmrem = new THREE.PMREMGenerator(this.renderer);
        const room = new RoomEnvironment();
        this.environmentMap = pmrem.fromScene(room, 0.04).texture;
        room.dispose();
        pmrem.dispose();
        this.scene.environment = this.environmentMap;
        this.scene.environmentIntensity = 0.35;

        this.camera.position.set(0, 30, 30);
        this.controls = new OrbitControls(
            this.camera,
            this.renderer.domElement,
        );
        Object.assign(this.controls, {
            enableDamping: true,
            dampingFactor: 0.08,
            maxPolarAngle: 1.38,
            minDistance: 3,
            maxDistance: 140,
            screenSpacePanning: false,
            zoomToCursor: true,
        });
        this.controls.mouseButtons = {
            LEFT: THREE.MOUSE.ROTATE,
            MIDDLE: THREE.MOUSE.DOLLY,
            RIGHT: THREE.MOUSE.PAN,
        };
        this.controls.touches = {
            ONE: THREE.TOUCH.ROTATE,
            TWO: THREE.TOUCH.DOLLY_PAN,
        };
        // Orbiting by hand stops a camera move.
        this.controls.addEventListener('start', () => {
            if (this.cameraTween) {
                this.cameraTween.cancelled = true;
                this.cameraTween = null;
            }
        });

        // The snapping grid shows while something is dragged (lines every 100 canvas units).
        const cell = 100 * UNIT;
        this.grid = new THREE.GridHelper(cell * 48, 48, '#e8c98a', '#e8c98a');
        const gridMaterial = this.grid.material as THREE.Material;
        gridMaterial.transparent = true;
        gridMaterial.opacity = 0;
        gridMaterial.depthWrite = false;
        this.grid.position.y = 0.02;
        this.scene.add(this.grid);

        const listen = <K extends keyof HTMLElementEventMap>(
            type: K,
            listener: (event: HTMLElementEventMap[K]) => void,
            options?: AddEventListenerOptions,
        ) => {
            container.addEventListener(type, listener, options);
            this.observers.push({
                disconnect: () =>
                    container.removeEventListener(type, listener, options),
            });
        };

        // Items are handled before the orbit controls see the press.
        listen('pointerdown', this.onPointerDown, { capture: true });
        listen('pointermove', this.onPointerMove);
        listen('pointerup', this.onPointerUp);
        listen('pointercancel', this.onPointerUp);
        listen('pointerleave', () => this.setHovered(null));
        // Plain scrolling scrolls the page; ctrl / pinch zooms (like the 2D plan).
        listen(
            'wheel',
            (event) => {
                if (!event.ctrlKey && !event.metaKey) {
                    event.stopPropagation();
                }
            },
            { capture: true },
        );

        const resize = new ResizeObserver(() => this.resize());
        resize.observe(container);
        this.observers.push(resize);
        this.resize();

        // Only animate while the map is on screen.
        const visibility = new IntersectionObserver(([entry]) =>
            this.renderer.setAnimationLoop(
                entry.isIntersecting ? this.frame : null,
            ),
        );
        visibility.observe(container);
        this.observers.push(visibility);
    }

    setCallbacks(callbacks: SceneCallbacks) {
        this.callbacks = callbacks;
    }

    setEditable(editable: boolean) {
        this.editable = editable;
    }

    /** Shows the tables and elements where the plan puts them; new ones drop in, removed ones shrink away. */
    setData(tables: EventTable[], elements: VenueElement[], plan: Plan) {
        const seen = new Set<string>();
        const first = this.firstData;
        this.firstData = false;
        const centre = this.planCentre(tables, elements, plan);

        tables.forEach((table) => {
            const key = `table:${table.id}`;
            const size = tableTokenSize(table.shape);
            const point = plan.tables[table.id];
            const position = toWorld(
                point.x + size.width / 2,
                point.y + size.height / 2,
            );
            const signature = [
                table.shape,
                table.seat_count,
                ...table.seats.map(
                    (seat) =>
                        `${seat.number}:${seat.guest_id ? seat.status : ''}`,
                ),
            ].join('|');
            seen.add(key);

            let item = this.items.get(key);

            if (!item) {
                item = this.createItem('table', table.id, size, position);
                this.fillTable(item, table, signature);
                // The first time, tables land one after another from the middle out.
                this.dropIn(
                    item,
                    first ? position.distanceTo(centre) * 35 + 250 : 0,
                );
            } else if (item.signature !== signature) {
                this.fillTable(item, table, signature);
                this.pop(item);
            }

            this.setTableLabel(item, table);

            if (this.press?.item !== item) {
                item.size = size;
                item.target.copy(position);
            }
        });

        elements.forEach((element) => {
            const key = `element:${element.id}`;
            const box = plan.elements[element.id] ?? element;
            const position = toWorld(
                box.x + box.width / 2,
                box.y + box.height / 2,
            );
            const signature = [element.kind, box.width, box.height].join('|');
            seen.add(key);

            let item = this.items.get(key);

            if (!item) {
                item = this.createItem('element', element.id, box, position);
                // New elements start at their turn rather than spinning into it.
                item.root.rotation.y = -THREE.MathUtils.degToRad(box.rotation);
                this.fillElement(item, element, box, signature);
                this.riseIn(
                    item,
                    first ? position.distanceTo(centre) * 25 + 100 : 0,
                );
            } else if (item.signature !== signature) {
                this.fillElement(item, element, box, signature);
                this.pop(item);
            }

            item.label.element.textContent = element.display_label;
            item.targetRotation = -THREE.MathUtils.degToRad(box.rotation);

            if (this.press?.item !== item) {
                item.size = { width: box.width, height: box.height };
                item.target.copy(position);
            }
        });

        for (const [key, item] of this.items) {
            if (!seen.has(key) && !item.removing) {
                this.remove(key, item);
            }
        }

        if (this.selected && !seen.has(`element:${this.selected.id}`)) {
            this.select(null);
        }

        this.fitRoom();

        if (first) {
            this.fit(true, true);
        }
    }

    /** Switches between the hall and the garden: the lights dim, the old decor sinks, the new one grows. */
    setVenue(venue: VenueSetting) {
        if (venue === this.venue) {
            return;
        }

        const previous = this.surroundings;
        this.venue = venue;

        if (!previous || this.reducedMotion) {
            this.buildSurroundings(previous !== null);

            return;
        }

        previous.decor.forEach((object, index) => {
            const from = object.scale.clone();
            this.animate(
                350,
                (p) => object.scale.copy(from).multiplyScalar(1 - p * 0.999),
                { delay: index * 8, ease: easeInBack },
            );
        });
        this.animate(400, (p) => (this.exposure = 1 - p * 0.75), {
            done: () => {
                this.buildSurroundings(true);
                this.animate(700, (p) => (this.exposure = 0.25 + p * 0.75));
            },
        });
    }

    /** Frames everything on the plan. */
    fit(animate = true, intro = false) {
        this.scene.updateMatrixWorld();
        const box = new THREE.Box3();

        for (const item of this.items.values()) {
            if (!item.removing) {
                box.expandByPoint(item.target);
                box.expandByObject(item.hit);
            }
        }

        if (box.isEmpty()) {
            box.set(new THREE.Vector3(-8, 0, -5), new THREE.Vector3(8, 1, 5));
        }

        const target = box.getCenter(new THREE.Vector3()).setY(0);
        const size = box.getSize(new THREE.Vector3());
        const radius = Math.max(4, Math.hypot(size.x, size.z) / 2);
        const vertical = THREE.MathUtils.degToRad(this.camera.fov) / 2;
        const horizontal = Math.atan(Math.tan(vertical) * this.camera.aspect);
        const distance = clamp(
            (radius / Math.sin(Math.min(vertical, horizontal))) * 0.75,
            6,
            120,
        );
        const current = new THREE.Spherical().setFromVector3(
            this.camera.position.clone().sub(this.controls.target),
        );
        const top = !intro && current.phi < 0.2;
        const spherical = new THREE.Spherical(
            distance,
            top ? 0.001 : 0.92,
            intro ? 0.3 : current.theta,
        );

        if (intro && !this.reducedMotion) {
            // Sweep in from high above.
            this.camera.position
                .copy(target)
                .add(
                    new THREE.Vector3().setFromSpherical(
                        new THREE.Spherical(distance * 1.7, 0.25, -0.9),
                    ),
                );
            this.controls.target.copy(target);
        }

        this.moveCamera(
            target,
            target.clone().add(new THREE.Vector3().setFromSpherical(spherical)),
            animate ? (intro ? 2200 : 700) : 0,
        );
    }

    /** Looks straight down (like the 2D plan), or back at an angle. */
    toggleTopView() {
        const target = this.controls.target.clone();
        const current = new THREE.Spherical().setFromVector3(
            this.camera.position.clone().sub(target),
        );
        const top = current.phi > 0.2;
        const next = new THREE.Spherical(
            current.radius,
            top ? 0.001 : 0.92,
            top ? 0 : current.theta,
        );

        this.moveCamera(
            target,
            target.clone().add(new THREE.Vector3().setFromSpherical(next)),
            700,
        );
    }

    zoom(factor: number) {
        const target = this.controls.target.clone();
        const offset = this.camera.position.clone().sub(target);
        offset.setLength(
            clamp(
                offset.length() / factor,
                this.controls.minDistance,
                this.controls.maxDistance,
            ),
        );
        this.moveCamera(target, target.clone().add(offset), 300);
    }

    /** The canvas point in the middle of the view (where new elements go). */
    centre(): Point {
        return toCanvas(this.controls.target);
    }

    /** Turns an element (and moves it, if turning nudged it); it glides there. */
    rotateElement(id: string, box: ElementBox) {
        const item = this.items.get(`element:${id}`);

        if (item) {
            item.targetRotation = -THREE.MathUtils.degToRad(box.rotation);
            item.target.copy(
                toWorld(box.x + box.width / 2, box.y + box.height / 2),
            );
        }
    }

    select(id: string | null) {
        const item = id ? (this.items.get(`element:${id}`) ?? null) : null;

        if (item === this.selected) {
            return;
        }

        this.selected = item;
        this.callbacks.onSelectElement(item?.id ?? null);

        if (!item) {
            this.callbacks.onSelectionMove(null);
        }
    }

    dispose() {
        this.renderer.setAnimationLoop(null);
        this.observers.forEach((observer) => observer.disconnect());
        this.controls.dispose();
        this.timer.dispose();

        for (const item of this.items.values()) {
            disposeObject(item.root, this.kit);
        }

        this.items.clear();
        this.disposeSurroundings(this.surroundings);
        this.grid.geometry.dispose();
        (this.grid.material as THREE.Material).dispose();
        this.environmentMap.dispose();
        this.kit.dispose();
        this.renderer.dispose();
        this.renderer.forceContextLoss();
        this.renderer.domElement.remove();
        this.labels.domElement.remove();
    }

    // Building

    private createItem(
        kind: ItemKind,
        id: string,
        size: { width: number; height: number },
        position: THREE.Vector3,
    ): Item {
        const root = new THREE.Group();
        const lift = new THREE.Group();
        const content = new THREE.Group();
        root.position.copy(position);
        lift.add(content);
        root.add(lift);

        const hit = new THREE.Mesh(
            new THREE.BoxGeometry(1, 1, 1),
            this.kit.hit,
        );
        const halo = new THREE.Mesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({
                color: '#e8c98a',
                transparent: true,
                opacity: 0,
                depthWrite: false,
            }),
        );
        halo.rotation.x = -Math.PI / 2;
        halo.position.y = 0.015;
        halo.renderOrder = 1;

        const element = document.createElement('div');
        element.className =
            kind === 'table'
                ? 'pointer-events-none flex items-center gap-1.5 whitespace-nowrap rounded-full bg-black/65 px-2.5 py-1 text-[11px] font-semibold text-white shadow-lg ring-1 ring-white/15 backdrop-blur-sm transition-transform duration-200'
                : 'pointer-events-none whitespace-nowrap rounded-md bg-white/85 px-2 py-0.5 text-[10px] font-bold tracking-wider text-neutral-800 uppercase shadow ring-1 ring-black/5 backdrop-blur-sm transition-transform duration-200';
        const label = new CSS2DObject(element);
        label.center.set(0.5, 1);
        root.add(hit, halo, label);
        this.scene.add(root);

        const item: Item = {
            kind,
            id,
            signature: '',
            size,
            root,
            lift,
            content,
            hit,
            halo,
            label,
            ticks: [],
            target: position.clone(),
            targetRotation: 0,
            liftTarget: 0,
            removing: false,
        };
        hit.userData.item = item;
        this.items.set(`${kind}:${id}`, item);

        return item;
    }

    private replaceContent(item: Item, built: Built, signature: string) {
        item.content.children.forEach((child) =>
            disposeObject(child, this.kit),
        );
        item.content.clear();
        item.content.add(built.object);
        item.ticks = built.ticks;
        item.signature = signature;
    }

    private fillTable(item: Item, table: EventTable, signature: string) {
        const built = buildTable(this.kit, table);
        this.replaceContent(item, built, signature);
        item.content.userData.chairs = built.chairs;
        item.hit.scale.set(built.radius * 2, 1.4, built.radius * 2);
        item.hit.position.y = 0.7;
        item.halo.geometry.dispose();
        item.halo.geometry = new THREE.CircleGeometry(built.radius + 0.2, 48);
        item.label.position.set(0, built.height + 0.35, 0);
    }

    private fillElement(
        item: Item,
        element: VenueElement,
        box: { width: number; height: number },
        signature: string,
    ) {
        const width = box.width * UNIT;
        const depth = box.height * UNIT;
        const built = buildVenueElement(this.kit, element.kind, width, depth);
        this.replaceContent(item, built, signature);
        item.hit.scale.set(width, built.height, depth);
        item.hit.position.y = built.height / 2;
        item.halo.geometry.dispose();
        item.halo.geometry = new THREE.PlaneGeometry(width + 0.5, depth + 0.5);
        item.label.position.set(0, built.height + 0.3, 0);
    }

    private setTableLabel(item: Item, table: EventTable) {
        const seated = table.seats.filter((seat) => seat.guest_id).length;
        const name = document.createElement('span');
        name.textContent = table.name;
        const count = document.createElement('span');
        count.className = 'font-medium text-white/65 tabular-nums';
        count.textContent = `${seated}/${table.seat_count}`;
        item.label.element.replaceChildren(name, count);
    }

    private buildSurroundings(entrance: boolean) {
        this.disposeSurroundings(this.surroundings);

        const surroundings =
            this.venue === 'outdoor'
                ? buildOutdoor(this.kit, this.room, this.reducedMotion)
                : buildIndoor(this.kit, this.room);
        this.surroundings = surroundings;
        this.scene.add(surroundings.group);
        this.scene.background = surroundings.background;
        this.scene.fog = surroundings.fog;
        this.scene.environmentIntensity = this.venue === 'outdoor' ? 0.5 : 0.35;

        if (entrance) {
            surroundings.decor.forEach((object, index) => {
                const to = object.scale.clone();
                this.animate(
                    600,
                    (p) =>
                        object.scale
                            .copy(to)
                            .multiplyScalar(Math.max(0.001, p)),
                    { delay: 80 + index * 14, ease: easeOutBack },
                );
            });
        }
    }

    private disposeSurroundings(surroundings: Surroundings | null) {
        if (!surroundings) {
            return;
        }

        this.scene.remove(surroundings.group);
        disposeObject(surroundings.group, this.kit);

        if (
            surroundings.background instanceof THREE.Texture &&
            !this.kit.shared.has(surroundings.background)
        ) {
            surroundings.background.dispose();
        }
    }

    /** The room hugs what's on the plan (with room to walk round); rebuilt when that changes. */
    private fitRoom() {
        const box = new THREE.Box3();

        for (const item of this.items.values()) {
            if (!item.removing) {
                // Wide enough for any turn.
                const reach = Math.max(item.hit.scale.x, item.hit.scale.z) / 2;
                const half = new THREE.Vector3(reach, 0, reach);
                box.expandByPoint(item.target.clone().sub(half));
                box.expandByPoint(item.target.clone().add(half));
            }
        }

        const padding = 4;
        const room: RoomBounds = box.isEmpty()
            ? { minX: -9, maxX: 9, minZ: -6, maxZ: 6 }
            : {
                  minX: Math.floor((box.min.x - padding) / 2) * 2,
                  maxX: Math.ceil((box.max.x + padding) / 2) * 2,
                  minZ: Math.floor((box.min.z - padding) / 2) * 2,
                  maxZ: Math.ceil((box.max.z + padding) / 2) * 2,
              };

        // At least 18 × 12 m, so a few tables don't sit in a cupboard.
        const grow = (min: number, max: number, wanted: number) => {
            const extra = Math.max(0, wanted - (max - min)) / 2;

            return [min - extra, max + extra];
        };
        [room.minX, room.maxX] = grow(room.minX, room.maxX, 18);
        [room.minZ, room.maxZ] = grow(room.minZ, room.maxZ, 12);

        const same = (Object.keys(room) as (keyof RoomBounds)[]).every(
            (key) => Math.abs(room[key] - this.room[key]) < 0.01,
        );
        this.room = room;

        if (this.venue && (!same || !this.surroundings)) {
            this.buildSurroundings(false);
        }
    }

    private planCentre(
        tables: EventTable[],
        elements: VenueElement[],
        plan: Plan,
    ): THREE.Vector3 {
        const points = [
            ...tables.map((table) => plan.tables[table.id]),
            ...elements.map((element) => plan.elements[element.id] ?? element),
        ];

        if (points.length === 0) {
            return new THREE.Vector3();
        }

        return toWorld(
            points.reduce((sum, point) => sum + point.x, 0) / points.length,
            points.reduce((sum, point) => sum + point.y, 0) / points.length,
        );
    }

    // Animation

    private animate(
        duration: number,
        update: (progress: number) => void,
        {
            delay = 0,
            ease = easeOutCubic,
            done,
        }: {
            delay?: number;
            ease?: (t: number) => number;
            done?: () => void;
        } = {},
    ): Tween | null {
        if (this.reducedMotion || duration === 0) {
            update(1);
            done?.();

            return null;
        }

        update(0);
        const tween: Tween = {
            start: performance.now() + delay,
            duration,
            update: (p) => update(ease(p)),
            done,
        };
        this.tweens.push(tween);

        return tween;
    }

    private dropIn(item: Item, delay: number) {
        const chairs = (item.content.userData.chairs ?? []) as THREE.Group[];
        this.animate(900, (p) => (item.content.position.y = (1 - p) * 6), {
            delay,
            ease: easeOutBounce,
        });
        chairs.forEach((chair, index) =>
            this.animate(
                380,
                (p) => chair.scale.setScalar(Math.max(0.001, p)),
                {
                    delay: delay + 550 + index * 35,
                    ease: easeOutBack,
                },
            ),
        );
        this.fadeLabel(item, delay + 700);
    }

    private riseIn(item: Item, delay: number) {
        this.animate(
            700,
            (p) =>
                item.content.scale.set(
                    0.6 + p * 0.4,
                    Math.max(0.001, p),
                    0.6 + p * 0.4,
                ),
            { delay, ease: easeOutBack },
        );
        this.fadeLabel(item, delay + 500);
    }

    private pop(item: Item) {
        this.animate(
            380,
            (p) => item.content.scale.setScalar(0.85 + p * 0.15),
            { ease: easeOutBack },
        );
    }

    private fadeLabel(item: Item, delay: number) {
        const style = item.label.element.style;
        this.animate(400, (p) => (style.opacity = String(p)), { delay });
    }

    private remove(key: string, item: Item) {
        item.removing = true;

        if (this.hovered === item) {
            this.setHovered(null);
        }

        this.animate(
            320,
            (p) => {
                item.content.scale.setScalar(Math.max(0.001, 1 - p));
                item.label.element.style.opacity = String(1 - p);
            },
            {
                ease: easeInBack,
                done: () => {
                    this.scene.remove(item.root);
                    disposeObject(item.root, this.kit);
                    this.items.delete(key);
                },
            },
        );
    }

    private moveCamera(
        target: THREE.Vector3,
        position: THREE.Vector3,
        duration: number,
    ) {
        if (this.cameraTween) {
            this.cameraTween.cancelled = true;
        }

        const fromTarget = this.controls.target.clone();
        const fromPosition = this.camera.position.clone();
        this.cameraTween = this.animate(
            duration,
            (p) => {
                this.controls.target.lerpVectors(fromTarget, target, p);
                this.camera.position.lerpVectors(fromPosition, position, p);
            },
            {
                ease: easeInOutCubic,
                done: () => (this.cameraTween = null),
            },
        );
    }

    private frame = () => {
        const now = performance.now();
        this.timer.update();
        const delta = Math.min(this.timer.getDelta(), 0.1);
        const time = this.timer.getElapsed();

        // Tweens started by a finishing one (`done`) join the next frame.
        const running = this.tweens;
        this.tweens = [];
        const kept = running.filter((tween) => {
            if (tween.cancelled) {
                return false;
            }

            const progress = (now - tween.start) / tween.duration;

            if (progress < 0) {
                return true;
            }

            tween.update(Math.min(1, progress));

            if (progress >= 1) {
                tween.done?.();

                return false;
            }

            return true;
        });
        this.tweens = [...kept, ...this.tweens];

        if (!this.reducedMotion) {
            this.surroundings?.ticks.forEach((tick) => tick(time));

            for (const item of this.items.values()) {
                item.ticks.forEach((tick) => tick(time));
            }
        }

        const glide = 1 - Math.exp(-delta * 16);
        const settle = 1 - Math.exp(-delta * 10);

        for (const item of this.items.values()) {
            item.root.position.lerp(item.target, glide);
            // The short way round.
            const turnLeft =
                THREE.MathUtils.euclideanModulo(
                    item.targetRotation - item.root.rotation.y + Math.PI,
                    Math.PI * 2,
                ) - Math.PI;
            item.root.rotation.y += turnLeft * settle;
            item.lift.position.y +=
                (item.liftTarget - item.lift.position.y) * settle;
            const active =
                item === this.hovered ||
                item === this.selected ||
                item === this.press?.item;
            const material = item.halo.material as THREE.MeshBasicMaterial;
            const wanted = active
                ? 0.35 +
                  (item === this.selected ? Math.sin(time * 4) * 0.12 : 0)
                : 0;
            material.opacity += (wanted - material.opacity) * settle;
            item.halo.visible = material.opacity > 0.01;
        }

        const gridMaterial = this.grid.material as THREE.Material;
        gridMaterial.opacity +=
            ((this.press?.moved ? 0.22 : 0) - gridMaterial.opacity) * settle;
        this.grid.visible = gridMaterial.opacity > 0.01;

        this.fadeWalls(settle);
        this.keepTargetInRoom();
        this.controls.update(delta);
        this.renderer.toneMappingExposure =
            (this.surroundings?.exposure ?? 1) * this.exposure;
        this.renderer.render(this.scene, this.camera);
        this.labels.render(this.scene, this.camera);
        this.reportSelection();
    };

    /** Walls between the camera and the room fade, so the hall reads like a doll's house. */
    private fadeWalls(settle: number) {
        if (!this.surroundings) {
            return;
        }

        for (const wall of this.surroundings.cutaway) {
            const toCamera = this.camera.position
                .clone()
                .sub(wall.object.position);
            const facing = toCamera.dot(wall.outward) > 0;
            const wanted = facing ? 0.08 : 1;

            for (const material of wall.materials) {
                material.opacity += (wanted - material.opacity) * settle;
                material.depthWrite = material.opacity > 0.9;
            }

            wall.object.traverse((child) => {
                child.castShadow = !facing;
            });
        }
    }

    private keepTargetInRoom() {
        const target = this.controls.target;
        const margin = 6;
        target.x = clamp(
            target.x,
            this.room.minX - margin,
            this.room.maxX + margin,
        );
        target.z = clamp(
            target.z,
            this.room.minZ - margin,
            this.room.maxZ + margin,
        );
        target.y = 0;
    }

    private reportSelection() {
        if (!this.selected) {
            return;
        }

        const point = this.selected.label.getWorldPosition(new THREE.Vector3());
        point.project(this.camera);
        const { clientWidth, clientHeight } = this.container;

        this.callbacks.onSelectionMove(
            point.z > 1
                ? null
                : {
                      x: ((point.x + 1) / 2) * clientWidth,
                      y: ((1 - point.y) / 2) * clientHeight,
                  },
        );
    }

    private resize() {
        const { clientWidth: width, clientHeight: height } = this.container;

        if (width === 0 || height === 0) {
            return;
        }

        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height, false);
        this.labels.setSize(width, height);
    }

    // Pointer

    private pointer(event: PointerEvent): THREE.Vector2 {
        const rect = this.renderer.domElement.getBoundingClientRect();

        return new THREE.Vector2(
            ((event.clientX - rect.left) / rect.width) * 2 - 1,
            -((event.clientY - rect.top) / rect.height) * 2 + 1,
        );
    }

    private pick(event: PointerEvent): Item | null {
        this.raycaster.setFromCamera(this.pointer(event), this.camera);
        const hits = this.raycaster.intersectObjects(
            [...this.items.values()]
                .filter((item) => !item.removing)
                .map((item) => item.hit),
            false,
        );

        // Tables win over the element they stand on (a table on the dance floor).
        const tables = hits.filter(
            (hit) => (hit.object.userData.item as Item).kind === 'table',
        );

        return ((tables[0] ?? hits[0])?.object.userData.item as Item) ?? null;
    }

    private floorPoint(event: PointerEvent): THREE.Vector3 | null {
        this.raycaster.setFromCamera(this.pointer(event), this.camera);

        return this.raycaster.ray.intersectPlane(
            this.floor,
            new THREE.Vector3(),
        );
    }

    private setHovered(item: Item | null) {
        if (item === this.hovered) {
            return;
        }

        if (this.hovered) {
            this.hovered.liftTarget = 0;
            this.hovered.label.element.style.transform = '';
        }

        this.hovered = item;

        if (item) {
            item.liftTarget = 0.08;
        }

        this.container.style.cursor = item
            ? this.editable
                ? 'grab'
                : 'pointer'
            : '';
    }

    private onPointerDown = (event: PointerEvent) => {
        if (event.button !== 0) {
            return;
        }

        const item = this.pick(event);

        if (!item) {
            this.emptyPress = { x: event.clientX, y: event.clientY };

            return;
        }

        // The orbit controls never see a press on a table or element.
        event.stopPropagation();
        this.container.setPointerCapture(event.pointerId);
        const floor = this.floorPoint(event) ?? item.root.position.clone();
        this.press = {
            item,
            pointerId: event.pointerId,
            start: { x: event.clientX, y: event.clientY },
            offset: item.target.clone().sub(floor),
            moved: false,
        };
    };

    private onPointerMove = (event: PointerEvent) => {
        const press = this.press;

        if (!press) {
            if (event.buttons === 0 && event.pointerType === 'mouse') {
                this.setHovered(this.pick(event));
            }

            return;
        }

        if (press.pointerId !== event.pointerId) {
            return;
        }

        if (
            !press.moved &&
            Math.hypot(
                event.clientX - press.start.x,
                event.clientY - press.start.y,
            ) < 5
        ) {
            return;
        }

        if (!this.editable) {
            return;
        }

        const floor = this.floorPoint(event);

        if (!floor) {
            return;
        }

        press.moved = true;
        press.item.liftTarget = 0.6;
        this.container.style.cursor = 'grabbing';

        const { size } = press.item;
        const centre = toCanvas(floor.add(press.offset));
        const x = clamp(
            snap(centre.x - size.width / 2),
            0,
            CANVAS.width - size.width,
        );
        const y = clamp(
            snap(centre.y - size.height / 2),
            0,
            CANVAS.height - size.height,
        );
        press.item.target.copy(
            toWorld(x + size.width / 2, y + size.height / 2),
        );
    };

    private onPointerUp = (event: PointerEvent) => {
        const press = this.press;
        const emptyPress = this.emptyPress;
        this.emptyPress = null;

        if (!press) {
            // A tap on the empty floor clears the selection.
            if (
                emptyPress &&
                Math.hypot(
                    event.clientX - emptyPress.x,
                    event.clientY - emptyPress.y,
                ) < 5
            ) {
                this.select(null);
            }

            return;
        }

        if (press.pointerId !== event.pointerId) {
            return;
        }

        this.press = null;

        if (this.container.hasPointerCapture(event.pointerId)) {
            this.container.releasePointerCapture(event.pointerId);
        }

        const { item } = press;
        item.liftTarget = item === this.hovered ? 0.08 : 0;
        this.container.style.cursor = item === this.hovered ? 'grab' : '';

        if (event.type === 'pointercancel') {
            return;
        }

        if (!press.moved) {
            if (item.kind === 'table') {
                this.select(null);
                this.callbacks.onOpenTable(item.id);
            } else {
                this.select(this.selected === item ? null : item.id);
            }

            return;
        }

        const topLeft = toCanvas(item.target);
        this.callbacks.onMove(item.kind, item.id, {
            x: Math.round(topLeft.x - item.size.width / 2),
            y: Math.round(topLeft.y - item.size.height / 2),
        });
    };
}
