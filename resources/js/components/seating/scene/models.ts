import * as THREE from 'three';
import { mergeGeometries } from 'three/examples/jsm/utils/BufferGeometryUtils.js';

import type { SeatTone } from '@/components/seating/seat-colors';
import { seatTone } from '@/components/seating/seat-colors';
import {
    danceFloorTexture,
    glowTexture,
    grassTexture,
    skyTexture,
    woodFloorTexture,
} from '@/components/seating/scene/textures';
import type { EventTable, TableShape, VenueElementKind } from '@/types';

/**
 * The 3D models of the floor plan, built from primitives: tables dressed
 * with cloths, plates and centrepieces, chairs whose cushions show the RSVP
 * status of whoever sits there, the venue elements (stage, poruwa, …), and
 * the room around them (a hall indoors, a garden outdoors).
 *
 * Sizes are in metres; y is up, the plan's x is x and its y is z.
 */

/** Something that moves on its own every frame (flames, lights, trees). */
export type Ticker = (time: number) => void;

export type Built = { object: THREE.Group; ticks: Ticker[]; height: number };

/** Cushion colours by seat status (the dashboard's seat colours, tuned for 3D light). */
const TONES: Record<SeatTone, string> = {
    confirmed: '#3f9d63',
    pending: '#dba73e',
    maybe: '#3e9ea3',
    declined: '#d4574b',
    empty: '#efe7d8',
};

const FLOWER_COLORS = ['#f7c6d0', '#ffffff', '#f2a7b5', '#fbe3c4', '#e98da0'];

/** Room each chair takes round a table. */
const CHAIR_PITCH = 0.6;
/** From a table's edge to the middle of its chairs. */
const CHAIR_OFFSET = 0.38;

/**
 * Materials, geometries and textures shared by every model, created once
 * per scene and disposed with it.
 */
export class Kit {
    readonly shared = new Set<{ dispose: () => void }>();

    readonly glow = this.keep(glowTexture());
    readonly wood = this.keep(woodFloorTexture());
    readonly grass = this.keep(grassTexture());
    readonly dance = this.keep(danceFloorTexture());

    readonly cloth = this.material({
        color: '#f6f1e7',
        roughness: 0.92,
        side: THREE.DoubleSide,
    });
    readonly gold = this.material({
        color: '#caa45d',
        metalness: 0.85,
        roughness: 0.3,
    });
    readonly silver = this.material({
        color: '#d9dde2',
        metalness: 1,
        roughness: 0.22,
    });
    readonly darkWood = this.material({ color: '#4a2f1d', roughness: 0.55 });
    readonly ivory = this.material({ color: '#efe6d6', roughness: 0.75 });
    readonly white = this.material({ color: '#ffffff', roughness: 0.4 });
    readonly velvet = this.material({ color: '#6d1f30', roughness: 0.85 });
    readonly black = this.material({ color: '#17151a', roughness: 0.5 });
    readonly marble = this.material({ color: '#f4f1ec', roughness: 0.18 });
    readonly greenery = this.material({
        color: '#3f6d3a',
        roughness: 0.9,
        flatShading: true,
    });
    readonly glass = this.keep(
        new THREE.MeshPhysicalMaterial({
            color: '#dff1f2',
            roughness: 0.05,
            transmission: 0.6,
            transparent: true,
            opacity: 0.45,
        }),
    );
    readonly flame = this.keep(
        new THREE.SpriteMaterial({
            map: this.glow,
            color: '#ffc46b',
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            transparent: true,
        }),
    );
    readonly flower = this.keep(
        new THREE.MeshStandardMaterial({ roughness: 0.7 }),
    );
    readonly hit = this.keep(new THREE.MeshBasicMaterial({ visible: false }));
    readonly cushions = Object.fromEntries(
        Object.entries(TONES).map(([tone, color]) => [
            tone,
            this.material({ color, roughness: 0.65 }),
        ]),
    ) as Record<SeatTone, THREE.MeshStandardMaterial>;

    readonly chairFrame = this.keep(chairFrameGeometry());
    readonly cushion = this.keep(new THREE.BoxGeometry(0.38, 0.05, 0.38));
    readonly plate = this.keep(
        new THREE.CylinderGeometry(0.12, 0.11, 0.015, 24),
    );
    readonly charger = this.keep(
        new THREE.CylinderGeometry(0.16, 0.16, 0.01, 24),
    );
    readonly candle = this.keep(
        new THREE.CylinderGeometry(0.02, 0.02, 0.12, 10),
    );
    readonly vase = this.keep(
        new THREE.CylinderGeometry(0.06, 0.045, 0.26, 16),
    );
    readonly bloom = this.keep(new THREE.IcosahedronGeometry(1, 1));

    material(parameters: THREE.MeshStandardMaterialParameters) {
        return this.keep(new THREE.MeshStandardMaterial(parameters));
    }

    keep<T extends { dispose: () => void }>(resource: T): T {
        this.shared.add(resource);

        return resource;
    }

    dispose() {
        this.shared.forEach((resource) => resource.dispose());
        this.shared.clear();
    }
}

/** Frees what a model created for itself (shared kit pieces stay). */
export function disposeObject(object: THREE.Object3D, kit: Kit) {
    object.traverse((child) => {
        const mesh = child as THREE.Mesh;

        if (mesh.geometry && !kit.shared.has(mesh.geometry)) {
            mesh.geometry.dispose();
        }

        const materials = Array.isArray(mesh.material)
            ? mesh.material
            : mesh.material
              ? [mesh.material]
              : [];
        materials.forEach((material) => {
            if (kit.shared.has(material)) {
                return;
            }

            // Cloned textures (floors) belong to their material.
            for (const map of [
                (material as THREE.MeshStandardMaterial).map,
                (material as THREE.MeshStandardMaterial).emissiveMap,
            ]) {
                if (map && !kit.shared.has(map)) {
                    map.dispose();
                }
            }

            material.dispose();
        });

        if ((child as { element?: Element }).element instanceof Element) {
            (child as unknown as { element: Element }).element.remove();
        }
    });
}

/** A gold Chiavari chair facing +z (its back towards -z). */
function chairFrameGeometry(): THREE.BufferGeometry {
    const parts: THREE.BufferGeometry[] = [];
    const add = (
        geometry: THREE.BufferGeometry,
        x: number,
        y: number,
        z: number,
    ) => {
        geometry.translate(x, y, z);
        parts.push(geometry);
    };

    for (const x of [-0.18, 0.18]) {
        for (const z of [-0.18, 0.18]) {
            add(new THREE.CylinderGeometry(0.016, 0.014, 0.46, 6), x, 0.23, z);
        }

        // Back posts.
        add(new THREE.CylinderGeometry(0.016, 0.016, 0.52, 6), x, 0.72, -0.19);
    }

    add(new THREE.BoxGeometry(0.42, 0.035, 0.42), 0, 0.46, 0);
    add(new THREE.BoxGeometry(0.4, 0.05, 0.03), 0, 0.97, -0.19);

    for (const y of [0.62, 0.74, 0.86]) {
        add(new THREE.BoxGeometry(0.36, 0.018, 0.018), 0, y, -0.19);
    }

    const merged = mergeGeometries(parts.map((part) => part.toNonIndexed()));
    parts.forEach((part) => part.dispose());

    return merged!;
}

function mesh(
    geometry: THREE.BufferGeometry,
    material: THREE.Material,
    position: [number, number, number] = [0, 0, 0],
    shadows = true,
): THREE.Mesh {
    const result = new THREE.Mesh(geometry, material);
    result.position.set(...position);
    result.castShadow = shadows;
    result.receiveShadow = shadows;

    return result;
}

/** A cluster of flowers as one instanced mesh. */
function flowers(
    kit: Kit,
    points: THREE.Vector3[],
    size = 0.055,
    colors = FLOWER_COLORS,
): THREE.InstancedMesh {
    const instanced = new THREE.InstancedMesh(
        kit.bloom,
        kit.flower,
        points.length,
    );
    const matrix = new THREE.Matrix4();
    const color = new THREE.Color();

    points.forEach((point, index) => {
        const scale = size * (0.75 + ((index * 37) % 10) / 20);
        matrix.makeScale(scale, scale * 0.8, scale).setPosition(point);
        instanced.setMatrixAt(index, matrix);
        instanced.setColorAt(index, color.set(colors[index % colors.length]));
    });
    instanced.castShadow = true;

    return instanced;
}

/** A candle flame: a glow that flickers. */
function flame(
    kit: Kit,
    ticks: Ticker[],
    position: THREE.Vector3,
    size = 0.12,
) {
    const sprite = new THREE.Sprite(kit.flame);
    sprite.position.copy(position);
    sprite.scale.setScalar(size);
    const phase = Math.random() * 10;
    ticks.push((time) => {
        const flicker =
            1 +
            Math.sin(time * 11 + phase) * 0.08 +
            Math.sin(time * 23 + phase) * 0.05;
        sprite.scale.set(size * flicker, size * 1.3 * flicker, 1);
    });

    return sprite;
}

/** Flowers in a loose dome, centred on a point. */
function bouquet(centre: THREE.Vector3, radius: number, count: number) {
    return Array.from({ length: count }, (_, index) => {
        const angle = index * 2.399;
        const ring = Math.sqrt((index + 0.5) / count) * radius;

        return new THREE.Vector3(
            centre.x + Math.cos(angle) * ring,
            centre.y + (radius - ring) * 0.6,
            centre.z + Math.sin(angle) * ring,
        );
    });
}

function centrepiece(kit: Kit, ticks: Ticker[], top: number): THREE.Group {
    const group = new THREE.Group();
    group.add(mesh(kit.vase, kit.glass, [0, top + 0.13, 0], false));
    group.add(
        flowers(kit, bouquet(new THREE.Vector3(0, top + 0.3, 0), 0.14, 14)),
    );

    for (let index = 0; index < 3; index++) {
        const angle = (index / 3) * Math.PI * 2 + 0.5;
        const x = Math.cos(angle) * 0.2;
        const z = Math.sin(angle) * 0.2;
        group.add(mesh(kit.candle, kit.white, [x, top + 0.06, z], false));
        group.add(flame(kit, ticks, new THREE.Vector3(x, top + 0.15, z), 0.09));
    }

    return group;
}

/** Splits the seats over the sides in proportion to their length (largest remainder). */
function shareSeats(count: number, sides: number[]): number[] {
    const total = sides.reduce((sum, side) => sum + side, 0);
    const exact = sides.map((side) => (count * side) / total);
    const shares = exact.map(Math.floor);
    let left = count - shares.reduce((sum, share) => sum + share, 0);
    const order = exact
        .map((value, index) => ({ index, rest: value - Math.floor(value) }))
        .sort((a, b) => b.rest - a.rest);

    for (const { index } of order) {
        if (left-- <= 0) {
            break;
        }

        shares[index] += 1;
    }

    return shares;
}

/** A chair's place; it turns by `angle` + π to face the table. */
type ChairSpot = { x: number; z: number; angle: number };

/** Where a table's top goes and where its chairs stand, seat 1 at the top, then clockwise. */
export function tableLayout(
    shape: TableShape,
    count: number,
): {
    top:
        | { kind: 'ellipse'; rx: number; rz: number }
        | { kind: 'rect'; w: number; d: number };
    chairs: ChairSpot[];
    radius: number;
} {
    if (shape === 'round' || shape === 'oval') {
        const squash = shape === 'oval' ? 0.62 : 1;
        // Chairs are spaced by distance along the ring, so the narrow ends of an oval aren't crowded.
        const ringX = Math.max(
            shape === 'oval' ? 1.35 : 0.95,
            (count * CHAIR_PITCH) / (Math.PI * (1 + squash)),
        );
        const ringZ = ringX * squash;
        const samples = 360;
        const ring = Array.from({ length: samples + 1 }, (_, index) => {
            const angle = (index / samples) * Math.PI * 2 - Math.PI / 2;

            return { x: ringX * Math.cos(angle), z: ringZ * Math.sin(angle) };
        });
        const lengths = [0];

        for (let index = 1; index <= samples; index++) {
            lengths.push(
                lengths[index - 1] +
                    Math.hypot(
                        ring[index].x - ring[index - 1].x,
                        ring[index].z - ring[index - 1].z,
                    ),
            );
        }

        const perimeter = lengths[samples];
        let sample = 0;
        const chairs = Array.from({ length: count }, (_, index) => {
            const wanted = (index / count) * perimeter;

            while (lengths[sample + 1] < wanted) {
                sample += 1;
            }

            const { x, z } = ring[sample];

            return { x, z, angle: Math.atan2(x, z) };
        });

        return {
            top: {
                kind: 'ellipse',
                rx: ringX - CHAIR_OFFSET,
                rz: ringZ - CHAIR_OFFSET,
            },
            chairs,
            radius: ringX + 0.35,
        };
    }

    const ratio = shape === 'rectangle' ? 2 : 1;
    let ringD = Math.max(
        shape === 'rectangle' ? 1.3 : 1.6,
        (count * CHAIR_PITCH) / (2 * (ratio + 1)),
    );
    let perSide = shareSeats(count, [
        ringD * ratio,
        ringD,
        ringD * ratio,
        ringD,
    ]);

    while (
        perSide.some(
            (seats, side) =>
                seats > 0 &&
                (ringD * (side % 2 === 0 ? ratio : 1)) / seats < CHAIR_PITCH,
        )
    ) {
        ringD *= 1.05;
        perSide = shareSeats(count, [
            ringD * ratio,
            ringD,
            ringD * ratio,
            ringD,
        ]);
    }

    const ringW = ringD * ratio;
    const chairs: ChairSpot[] = [];

    perSide.forEach((onSide, side) => {
        const length = side % 2 === 0 ? ringW : ringD;

        for (let index = 0; index < onSide; index++) {
            const along = (length * (index + 0.5)) / onSide;
            chairs.push(
                [
                    { x: -ringW / 2 + along, z: -ringD / 2, angle: Math.PI },
                    { x: ringW / 2, z: -ringD / 2 + along, angle: Math.PI / 2 },
                    { x: ringW / 2 - along, z: ringD / 2, angle: 0 },
                    {
                        x: -ringW / 2,
                        z: ringD / 2 - along,
                        angle: -Math.PI / 2,
                    },
                ][side],
            );
        }
    });

    return {
        top: {
            kind: 'rect',
            w: ringW - CHAIR_OFFSET * 2 + 0.2,
            d: ringD - CHAIR_OFFSET * 2 + 0.2,
        },
        chairs,
        radius: Math.hypot(ringW, ringD) / 2 + 0.3,
    };
}

export type BuiltTable = Built & {
    /** The chairs, in seat order, to pop in one by one. */
    chairs: THREE.Group[];
    radius: number;
};

const TABLE_HEIGHT = 0.76;

/** A dressed table with its chairs; each cushion shows its seat's status. */
export function buildTable(kit: Kit, table: EventTable): BuiltTable {
    const group = new THREE.Group();
    const ticks: Ticker[] = [];
    const layout = tableLayout(table.shape, table.seat_count);

    if (layout.top.kind === 'ellipse') {
        const { rx, rz } = layout.top;
        const body = new THREE.Group();
        body.scale.set(1, 1, rz / rx);
        body.add(
            mesh(
                new THREE.CylinderGeometry(
                    rx + 0.02,
                    rx + 0.13,
                    TABLE_HEIGHT - 0.02,
                    48,
                    1,
                    true,
                ),
                kit.cloth,
                [0, (TABLE_HEIGHT - 0.02) / 2, 0],
            ),
        );
        body.add(
            mesh(
                new THREE.CylinderGeometry(rx + 0.02, rx + 0.02, 0.03, 48),
                kit.cloth,
                [0, TABLE_HEIGHT, 0],
            ),
        );
        group.add(body);
    } else {
        const { w, d } = layout.top;
        group.add(
            mesh(
                new THREE.BoxGeometry(w + 0.08, TABLE_HEIGHT, d + 0.08),
                kit.cloth,
                [0, TABLE_HEIGHT / 2, 0],
            ),
        );
        // A runner down the middle.
        group.add(
            mesh(
                new THREE.BoxGeometry(
                    w > d ? w + 0.1 : 0.3,
                    0.005,
                    w > d ? 0.3 : d + 0.1,
                ),
                kit.velvet,
                [0, TABLE_HEIGHT + 0.004, 0],
                false,
            ),
        );
    }

    group.add(centrepiece(kit, ticks, TABLE_HEIGHT + 0.015));

    const seatsByNumber = new Map(
        table.seats.map((seat) => [seat.number, seat]),
    );
    const chairs = layout.chairs.map((spot, index) => {
        const seat = seatsByNumber.get(index + 1);
        const tone: SeatTone = seat?.guest_id ? seatTone(seat.status) : 'empty';
        const chair = new THREE.Group();
        chair.position.set(spot.x, 0, spot.z);
        chair.rotation.y = spot.angle + Math.PI;
        chair.add(mesh(kit.chairFrame, kit.gold));
        chair.add(mesh(kit.cushion, kit.cushions[tone], [0, 0.5, 0]));
        group.add(chair);

        // A place setting in front of the chair, on the table.
        const inward = 0.48;
        const px = spot.x + Math.sin(chair.rotation.y) * inward;
        const pz = spot.z + Math.cos(chair.rotation.y) * inward;
        group.add(
            mesh(kit.charger, kit.gold, [px, TABLE_HEIGHT + 0.02, pz], false),
        );
        group.add(
            mesh(kit.plate, kit.white, [px, TABLE_HEIGHT + 0.03, pz], false),
        );

        return chair;
    });

    return { object: group, ticks, height: 1.4, chairs, radius: layout.radius };
}

/** A venue element: footprint `width` × `depth` metres, centred on the origin. */
export function buildVenueElement(
    kit: Kit,
    kind: VenueElementKind,
    width: number,
    depth: number,
): Built {
    const group = new THREE.Group();
    const ticks: Ticker[] = [];
    const add = (object: THREE.Object3D) => group.add(object);
    const box = (
        w: number,
        h: number,
        d: number,
        material: THREE.Material,
        x = 0,
        y = h / 2,
        z = 0,
    ) => add(mesh(new THREE.BoxGeometry(w, h, d), material, [x, y, z]));
    let height = 1;

    switch (kind) {
        case 'stage': {
            box(width, 0.6, depth, kit.velvet);
            box(width, 0.04, depth, kit.darkWood, 0, 0.62);
            const back = -depth / 2 + 0.06;
            box(width * 0.92, 2.6, 0.08, kit.ivory, 0, 0.64 + 1.3, back);
            box(0.18, 2.8, 0.14, kit.gold, -width * 0.46, 0.64 + 1.4, back);
            box(0.18, 2.8, 0.14, kit.gold, width * 0.46, 0.64 + 1.4, back);
            const garland = Math.max(8, Math.round(width / 0.12));
            add(
                flowers(
                    kit,
                    Array.from({ length: garland }, (_, index) => {
                        const along = index / (garland - 1);

                        return new THREE.Vector3(
                            (along - 0.5) * width * 0.92,
                            3.24 - Math.sin(along * Math.PI) * 0.35,
                            back + 0.08,
                        );
                    }),
                    0.07,
                ),
            );
            box(
                Math.min(1.8, width * 0.35),
                0.3,
                0.4,
                kit.darkWood,
                0,
                0.15,
                depth / 2 + 0.2,
            );
            for (const side of [-1, 1]) {
                add(
                    flame(
                        kit,
                        ticks,
                        new THREE.Vector3(
                            side * width * 0.42,
                            0.75,
                            depth / 2 - 0.15,
                        ),
                        0.35,
                    ),
                );
            }
            height = 3.4;
            break;
        }
        case 'poruwa': {
            box(width, 0.22, depth, kit.white);
            box(width * 0.8, 0.22, depth * 0.8, kit.ivory, 0, 0.33);
            box(width * 0.82, 0.02, depth * 0.82, kit.gold, 0, 0.45);
            const pillarHeight = 2.2;
            const corners = [-1, 1].flatMap((x) =>
                [-1, 1].map((z) => [x * width * 0.36, z * depth * 0.36]),
            );
            const blooms: THREE.Vector3[] = [];

            for (const [x, z] of corners) {
                add(
                    mesh(
                        new THREE.CylinderGeometry(
                            0.05,
                            0.07,
                            pillarHeight,
                            12,
                        ),
                        kit.gold,
                        [x, 0.44 + pillarHeight / 2, z],
                    ),
                );
                blooms.push(
                    ...bouquet(
                        new THREE.Vector3(x, 0.44 + pillarHeight - 0.05, z),
                        0.16,
                        12,
                    ),
                );
                blooms.push(...bouquet(new THREE.Vector3(x, 0.5, z), 0.12, 6));
            }

            const roofRadius = Math.max(width, depth) * 0.6;
            const roof = mesh(
                new THREE.ConeGeometry(roofRadius, 0.9, 4, 1),
                kit.ivory,
                [0, 0.44 + pillarHeight + 0.45, 0],
            );
            roof.rotation.y = Math.PI / 4;
            add(roof);
            add(
                mesh(new THREE.SphereGeometry(0.09, 16, 12), kit.gold, [
                    0,
                    0.44 + pillarHeight + 0.95,
                    0,
                ]),
            );
            add(flowers(kit, blooms, 0.06));

            // The oil lamp (pahana) in front.
            add(
                mesh(new THREE.CylinderGeometry(0.04, 0.12, 1, 12), kit.gold, [
                    0,
                    0.5,
                    depth / 2 + 0.35,
                ]),
            );
            add(
                flame(
                    kit,
                    ticks,
                    new THREE.Vector3(0, 1.08, depth / 2 + 0.35),
                    0.22,
                ),
            );
            height = 3.6;
            break;
        }
        case 'dance_floor': {
            const tiles = kit.dance.clone();
            tiles.repeat.set(width / 1.2, depth / 1.2);
            tiles.needsUpdate = true;
            const floor = new THREE.MeshStandardMaterial({
                map: tiles,
                emissiveMap: tiles,
                emissive: '#ffffff',
                emissiveIntensity: 0.2,
                roughness: 0.15,
                metalness: 0.1,
            });
            box(width, 0.05, depth, floor);
            box(width + 0.08, 0.04, 0.06, kit.gold, 0, 0.03, -depth / 2);
            box(width + 0.08, 0.04, 0.06, kit.gold, 0, 0.03, depth / 2);
            box(0.06, 0.04, depth, kit.gold, -width / 2, 0.03);
            box(0.06, 0.04, depth, kit.gold, width / 2, 0.03);
            const color = new THREE.Color();
            ticks.push((time) => {
                floor.emissiveIntensity = 0.18 + Math.sin(time * 2) * 0.08;
                floor.emissive.copy(color.setHSL((time * 0.05) % 1, 0.5, 0.75));
            });

            // A mirror ball overhead.
            const ball = mesh(
                new THREE.IcosahedronGeometry(0.3, 2),
                new THREE.MeshStandardMaterial({
                    color: '#e8ecf0',
                    metalness: 1,
                    roughness: 0.1,
                    flatShading: true,
                }),
                [0, 4.2, 0],
            );
            add(ball);
            add(
                mesh(
                    new THREE.CylinderGeometry(0.008, 0.008, 1.6, 4),
                    kit.black,
                    [0, 5.3, 0],
                    false,
                ),
            );
            ticks.push((time) => (ball.rotation.y = time * 0.6));
            height = 4.5;
            break;
        }
        case 'buffet': {
            box(width, TABLE_HEIGHT, depth, kit.cloth);
            const dishes = Math.max(1, Math.floor(width / 0.7));

            for (let index = 0; index < dishes; index++) {
                const x = (index - (dishes - 1) / 2) * (width / dishes);
                box(0.42, 0.08, 0.3, kit.silver, x, TABLE_HEIGHT + 0.04);
                const lid = mesh(
                    new THREE.SphereGeometry(
                        0.2,
                        16,
                        8,
                        0,
                        Math.PI * 2,
                        0,
                        Math.PI / 2,
                    ),
                    kit.silver,
                    [x, TABLE_HEIGHT + 0.08, 0],
                );
                lid.scale.set(1.05, 0.55, 0.75);
                add(lid);
            }

            height = 1.2;
            break;
        }
        case 'bar': {
            const counterDepth = depth * 0.55;
            box(
                width,
                1.05,
                counterDepth,
                kit.darkWood,
                0,
                0.525,
                depth / 2 - counterDepth / 2,
            );
            box(
                width + 0.06,
                0.05,
                counterDepth + 0.08,
                kit.marble,
                0,
                1.08,
                depth / 2 - counterDepth / 2,
            );
            const strip = new THREE.MeshStandardMaterial({
                color: '#000000',
                emissive: '#ffc97a',
                emissiveIntensity: 1.5,
            });
            box(width - 0.1, 0.03, 0.01, strip, 0, 0.12, depth / 2 + 0.005);
            ticks.push(
                (time) =>
                    (strip.emissiveIntensity =
                        1.2 + Math.sin(time * 1.5) * 0.4),
            );
            box(width, 1.9, 0.28, kit.darkWood, 0, 0.95, -depth / 2 + 0.14);
            const bottleColors = [
                '#2f5d3a',
                '#7a2e1f',
                '#c8a24a',
                '#e8e8e8',
            ].map(
                (color) =>
                    new THREE.MeshStandardMaterial({
                        color,
                        roughness: 0.15,
                        metalness: 0.2,
                    }),
            );
            const bottle = new THREE.CylinderGeometry(0.035, 0.04, 0.26, 10);
            const bottles = Math.min(28, Math.floor(width / 0.16));

            for (const shelf of [1.25, 1.65]) {
                box(
                    width * 0.96,
                    0.02,
                    0.24,
                    kit.gold,
                    0,
                    shelf - 0.01,
                    -depth / 2 + 0.26,
                );

                for (let index = 0; index < bottles; index++) {
                    add(
                        mesh(
                            bottle,
                            bottleColors[(index + (shelf > 1.5 ? 1 : 0)) % 4],
                            [
                                (index - (bottles - 1) / 2) * 0.16,
                                shelf + 0.13,
                                -depth / 2 + 0.26,
                            ],
                            false,
                        ),
                    );
                }
            }

            height = 2;
            break;
        }
        case 'cake_table': {
            const radius = Math.min(width, depth) * 0.45;
            add(
                mesh(
                    new THREE.CylinderGeometry(
                        radius,
                        radius + 0.1,
                        TABLE_HEIGHT,
                        32,
                        1,
                        true,
                    ),
                    kit.cloth,
                    [0, TABLE_HEIGHT / 2, 0],
                ),
            );
            add(
                mesh(
                    new THREE.CylinderGeometry(radius, radius, 0.02, 32),
                    kit.cloth,
                    [0, TABLE_HEIGHT, 0],
                ),
            );
            let y = TABLE_HEIGHT + 0.01;

            for (const tier of [0.24, 0.18, 0.12]) {
                add(
                    mesh(
                        new THREE.CylinderGeometry(tier, tier, 0.16, 32),
                        kit.white,
                        [0, y + 0.08, 0],
                    ),
                );
                add(
                    mesh(
                        new THREE.TorusGeometry(tier, 0.012, 6, 32),
                        kit.gold,
                        [0, y + 0.02, 0],
                    ).rotateX(Math.PI / 2),
                );
                y += 0.16;
            }

            add(
                flowers(
                    kit,
                    bouquet(new THREE.Vector3(0, y + 0.02, 0), 0.1, 9),
                    0.045,
                ),
            );
            height = 1.5;
            break;
        }
        case 'dj': {
            box(width * 0.75, 1, depth * 0.5, kit.black, 0, 0.5, -depth * 0.1);
            const panel = new THREE.MeshStandardMaterial({
                color: '#000000',
                emissive: '#8a5cff',
                emissiveIntensity: 1.2,
            });
            box(
                width * 0.7,
                0.5,
                0.01,
                panel,
                0,
                0.55,
                -depth * 0.1 + depth * 0.25 + 0.006,
            );
            const hue = new THREE.Color();
            ticks.push((time) =>
                panel.emissive.copy(hue.setHSL((time * 0.08) % 1, 0.8, 0.5)),
            );

            for (const side of [-1, 1]) {
                const deck = mesh(
                    new THREE.CylinderGeometry(0.13, 0.13, 0.03, 24),
                    kit.silver,
                    [side * width * 0.16, 1.02, -depth * 0.1],
                );
                add(deck);
                ticks.push((time) => (deck.rotation.y = time * 3 * side));
                box(
                    0.38,
                    1.3,
                    0.38,
                    kit.black,
                    side * (width / 2 - 0.2),
                    0.65,
                    -depth * 0.1,
                );
            }

            height = 1.4;
            break;
        }
        case 'photo_booth': {
            const back = -depth / 2 + 0.06;
            box(width * 0.9, 2.3, 0.1, kit.greenery, 0, 1.15, back);
            const wall: THREE.Vector3[] = [];

            for (let row = 0; row < 12; row++) {
                for (let column = 0; column < Math.round(width * 6); column++) {
                    if ((row * 7 + column * 3) % 5 < 2) {
                        wall.push(
                            new THREE.Vector3(
                                ((column + 0.5) / (width * 6) - 0.5) *
                                    width *
                                    0.86,
                                0.2 + row * 0.18,
                                back + 0.07,
                            ),
                        );
                    }
                }
            }

            add(flowers(kit, wall, 0.06));
            const ring = mesh(
                new THREE.TorusGeometry(0.25, 0.025, 8, 32),
                new THREE.MeshStandardMaterial({
                    color: '#ffffff',
                    emissive: '#ffffff',
                    emissiveIntensity: 1.4,
                }),
                [0, 1.45, depth / 2 - 0.2],
            );
            add(ring);
            add(
                mesh(
                    new THREE.CylinderGeometry(0.015, 0.015, 1.3, 6),
                    kit.black,
                    [0, 0.65, depth / 2 - 0.2],
                ),
            );
            height = 2.4;
            break;
        }
        case 'entrance': {
            const span = Math.max(0.6, width * 0.45);
            const arch = mesh(
                new THREE.TorusGeometry(span, 0.05, 10, 48, Math.PI),
                kit.gold,
                [0, 1.5, 0],
            );
            add(arch);

            for (const side of [-1, 1]) {
                add(
                    mesh(
                        new THREE.CylinderGeometry(0.05, 0.05, 1.5, 10),
                        kit.gold,
                        [side * span, 0.75, 0],
                    ),
                );
            }

            add(
                flowers(
                    kit,
                    Array.from({ length: 42 }, (_, index) => {
                        const angle = (index / 41) * Math.PI;
                        const wobble = ((index * 13) % 5) * 0.015;

                        return new THREE.Vector3(
                            Math.cos(angle) * (span + wobble),
                            1.5 + Math.sin(angle) * (span + wobble),
                            ((index % 3) - 1) * 0.05,
                        );
                    }),
                    0.07,
                ),
            );
            const runner = mesh(
                new THREE.PlaneGeometry(Math.min(1.4, width * 0.5), depth),
                kit.velvet,
                [0, 0.012, 0],
                false,
            );
            runner.rotation.x = -Math.PI / 2;
            runner.receiveShadow = true;
            add(runner);
            height = 1.5 + span + 0.1;
            break;
        }
        case 'registration': {
            box(
                width * 0.85,
                TABLE_HEIGHT,
                depth * 0.5,
                kit.cloth,
                0,
                TABLE_HEIGHT / 2,
                -depth * 0.15,
            );
            box(
                0.3,
                0.04,
                0.22,
                kit.velvet,
                -width * 0.15,
                TABLE_HEIGHT + 0.02,
                -depth * 0.15,
            );
            add(
                flowers(
                    kit,
                    bouquet(
                        new THREE.Vector3(
                            width * 0.25,
                            TABLE_HEIGHT + 0.08,
                            -depth * 0.15,
                        ),
                        0.12,
                        10,
                    ),
                ),
            );
            // A welcome sign on an easel.
            const sign = mesh(
                new THREE.BoxGeometry(0.6, 0.85, 0.03),
                kit.ivory,
                [width * 0.42, 1.25, depth * 0.25],
            );
            sign.rotation.x = -0.12;
            add(sign);
            add(
                mesh(
                    new THREE.CylinderGeometry(0.012, 0.012, 1.6, 4),
                    kit.gold,
                    [width * 0.42, 0.8, depth * 0.25 + 0.1],
                ),
            );
            height = 1.7;
            break;
        }
        case 'restrooms': {
            box(width, 2.5, depth, kit.ivory);
            box(width + 0.2, 0.15, depth + 0.2, kit.darkWood, 0, 2.575);
            for (const side of [-1, 1]) {
                box(
                    0.7,
                    2,
                    0.04,
                    kit.darkWood,
                    side * width * 0.25,
                    1,
                    depth / 2 + 0.02,
                );
            }
            height = 2.7;
            break;
        }
        default: {
            box(width, 0.8, depth, kit.cloth);
            box(width + 0.04, 0.03, depth + 0.04, kit.gold, 0, 0.815);
            add(
                flowers(
                    kit,
                    bouquet(
                        new THREE.Vector3(0, 0.9, 0),
                        Math.min(0.3, Math.min(width, depth) / 3),
                        14,
                    ),
                ),
            );
            height = 1.2;
        }
    }

    return { object: group, ticks, height };
}

/** The room's floor area, in metres. */
export type RoomBounds = {
    minX: number;
    maxX: number;
    minZ: number;
    maxZ: number;
};

export type Surroundings = {
    group: THREE.Group;
    ticks: Ticker[];
    /** Pieces that pop in when the setting appears (walls, trees, poles). */
    decor: THREE.Object3D[];
    background: THREE.Color | THREE.Texture;
    fog: THREE.Fog;
    exposure: number;
    /** Lights the shadows come from. */
    sun: THREE.DirectionalLight;
    /** Walls between the camera and the room fade out so you can see in. */
    cutaway: {
        object: THREE.Object3D;
        outward: THREE.Vector3;
        materials: THREE.Material[];
    }[];
};

function sunLight(
    bounds: RoomBounds,
    color: string,
    intensity: number,
    from: THREE.Vector3,
) {
    const sun = new THREE.DirectionalLight(color, intensity);
    const centre = new THREE.Vector3(
        (bounds.minX + bounds.maxX) / 2,
        0,
        (bounds.minZ + bounds.maxZ) / 2,
    );
    const extent =
        Math.max(bounds.maxX - bounds.minX, bounds.maxZ - bounds.minZ) / 2 + 4;
    sun.position.copy(centre).add(from);
    sun.target.position.copy(centre);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    sun.shadow.bias = -0.0004;
    sun.shadow.normalBias = 0.02;
    Object.assign(sun.shadow.camera, {
        left: -extent,
        right: extent,
        top: extent,
        bottom: -extent,
        near: 1,
        far: 200,
    });
    sun.shadow.camera.updateProjectionMatrix();

    return sun;
}

/** An evening reception hall: oak floor, panelled walls with tall windows, chandeliers. */
export function buildIndoor(kit: Kit, bounds: RoomBounds): Surroundings {
    const group = new THREE.Group();
    const ticks: Ticker[] = [];
    const decor: THREE.Object3D[] = [];
    const cutaway: Surroundings['cutaway'] = [];
    const width = bounds.maxX - bounds.minX;
    const depth = bounds.maxZ - bounds.minZ;
    const cx = (bounds.minX + bounds.maxX) / 2;
    const cz = (bounds.minZ + bounds.maxZ) / 2;

    const wood = kit.wood.clone();
    wood.repeat.set(width / 2.4, depth / 2.4);
    wood.needsUpdate = true;
    const floor = mesh(
        new THREE.PlaneGeometry(width, depth),
        new THREE.MeshStandardMaterial({
            map: wood,
            roughness: 0.38,
            metalness: 0.05,
        }),
        [cx, 0, cz],
    );
    floor.rotation.x = -Math.PI / 2;
    floor.castShadow = false;
    group.add(floor);

    const base = mesh(
        new THREE.PlaneGeometry(400, 400),
        new THREE.MeshStandardMaterial({ color: '#1a130d', roughness: 1 }),
        [cx, -0.02, cz],
        false,
    );
    base.rotation.x = -Math.PI / 2;
    group.add(base);

    const wallHeight = 4.2;
    const walls: {
        x: number;
        z: number;
        length: number;
        rotation: number;
        outward: THREE.Vector3;
    }[] = [
        {
            x: cx,
            z: bounds.minZ,
            length: width,
            rotation: 0,
            outward: new THREE.Vector3(0, 0, -1),
        },
        {
            x: cx,
            z: bounds.maxZ,
            length: width,
            rotation: Math.PI,
            outward: new THREE.Vector3(0, 0, 1),
        },
        {
            x: bounds.minX,
            z: cz,
            length: depth,
            rotation: Math.PI / 2,
            outward: new THREE.Vector3(-1, 0, 0),
        },
        {
            x: bounds.maxX,
            z: cz,
            length: depth,
            rotation: -Math.PI / 2,
            outward: new THREE.Vector3(1, 0, 0),
        },
    ];

    for (const spec of walls) {
        const wall = new THREE.Group();
        wall.position.set(spec.x, 0, spec.z);
        wall.rotation.y = spec.rotation;
        // Each wall has its own materials, so it can fade on its own.
        const plaster = new THREE.MeshStandardMaterial({
            color: '#efe4d2',
            roughness: 0.9,
            transparent: true,
        });
        const panel = new THREE.MeshStandardMaterial({
            color: '#b99d7a',
            roughness: 0.7,
            transparent: true,
        });
        const trim = new THREE.MeshStandardMaterial({
            color: '#caa45d',
            metalness: 0.8,
            roughness: 0.3,
            transparent: true,
        });
        const glass = new THREE.MeshStandardMaterial({
            color: '#0d1830',
            emissive: '#29457a',
            emissiveIntensity: 0.7,
            roughness: 0.1,
            transparent: true,
        });

        wall.add(
            mesh(
                new THREE.BoxGeometry(spec.length + 0.3, wallHeight, 0.25),
                plaster,
                [0, wallHeight / 2, -0.12],
            ),
        );
        wall.add(
            mesh(
                new THREE.BoxGeometry(spec.length, 1.1, 0.06),
                panel,
                [0, 0.55, 0.03],
            ),
        );
        wall.add(
            mesh(
                new THREE.BoxGeometry(spec.length, 0.05, 0.08),
                trim,
                [0, 1.12, 0.04],
                false,
            ),
        );
        wall.add(
            mesh(
                new THREE.BoxGeometry(spec.length, 0.08, 0.1),
                trim,
                [0, wallHeight - 0.05, 0.04],
                false,
            ),
        );

        const windows = Math.max(1, Math.floor(spec.length / 5));

        for (let index = 0; index < windows; index++) {
            const x = (index - (windows - 1) / 2) * (spec.length / windows);
            wall.add(
                mesh(
                    new THREE.BoxGeometry(1.2, 2.3, 0.04),
                    glass,
                    [x, 2.55, 0.01],
                    false,
                ),
            );
            wall.add(
                mesh(
                    new THREE.BoxGeometry(1.36, 0.06, 0.08),
                    trim,
                    [x, 3.73, 0.03],
                    false,
                ),
            );
            wall.add(
                mesh(
                    new THREE.BoxGeometry(0.22, wallHeight - 1.15, 0.12),
                    plaster,
                    [
                        x + spec.length / windows / 2,
                        1.15 + (wallHeight - 1.15) / 2,
                        0.04,
                    ],
                ),
            );
        }

        group.add(wall);
        decor.push(wall);
        cutaway.push({
            object: wall,
            outward: spec.outward,
            materials: [plaster, panel, trim, glass],
        });
    }

    // Chandeliers on a grid, about every 9 m (at most six lights).
    const across = Math.max(1, Math.min(3, Math.round(width / 9)));
    const along = Math.max(1, Math.min(2, Math.round(depth / 9)));

    for (let i = 0; i < across; i++) {
        for (let j = 0; j < along; j++) {
            const x = bounds.minX + ((i + 0.5) * width) / across;
            const z = bounds.minZ + ((j + 0.5) * depth) / along;
            const chandelier = new THREE.Group();
            chandelier.position.set(x, 3.6, z);
            chandelier.add(
                mesh(
                    new THREE.TorusGeometry(0.55, 0.025, 8, 40),
                    kit.gold,
                    [0, 0, 0],
                    false,
                ).rotateX(Math.PI / 2),
            );
            chandelier.add(
                mesh(
                    new THREE.TorusGeometry(0.3, 0.02, 8, 32),
                    kit.gold,
                    [0, 0.25, 0],
                    false,
                ).rotateX(Math.PI / 2),
            );
            chandelier.add(
                mesh(
                    new THREE.CylinderGeometry(0.01, 0.01, 2, 4),
                    kit.gold,
                    [0, 1.1, 0],
                    false,
                ),
            );

            for (let bulb = 0; bulb < 10; bulb++) {
                const angle = (bulb / 10) * Math.PI * 2;
                chandelier.add(
                    flame(
                        kit,
                        ticks,
                        new THREE.Vector3(
                            Math.cos(angle) * 0.55,
                            0.08,
                            Math.sin(angle) * 0.55,
                        ),
                        0.16,
                    ),
                );
            }

            const crystals = Array.from({ length: 18 }, (_, index) => {
                const angle = (index / 18) * Math.PI * 2;

                return new THREE.Vector3(
                    Math.cos(angle) * 0.42,
                    -0.12 - (index % 3) * 0.08,
                    Math.sin(angle) * 0.42,
                );
            });
            chandelier.add(flowers(kit, crystals, 0.03, ['#fff8ec']));

            const light = new THREE.PointLight('#ffd49a', 14, 14, 1.4);
            chandelier.add(light);
            const phase = i * 3 + j;
            ticks.push((time) => {
                light.intensity = 14 + Math.sin(time * 3 + phase) * 0.6;
                chandelier.rotation.y = Math.sin(time * 0.3 + phase) * 0.05;
            });
            group.add(chandelier);
            decor.push(chandelier);
        }
    }

    group.add(new THREE.HemisphereLight('#ffe7c7', '#4a3524', 1.25));
    const sun = sunLight(bounds, '#ffe1b3', 1.6, new THREE.Vector3(-8, 22, 10));
    group.add(sun, sun.target);

    return {
        group,
        ticks,
        decor,
        background: new THREE.Color('#15100b'),
        fog: new THREE.Fog('#15100b', 45, 140),
        exposure: 1,
        sun,
        cutaway,
    };
}

/** A garden at golden hour: lawn, trees, and string lights over the tables. */
export function buildOutdoor(
    kit: Kit,
    bounds: RoomBounds,
    reducedMotion: boolean,
): Surroundings {
    const group = new THREE.Group();
    const ticks: Ticker[] = [];
    const decor: THREE.Object3D[] = [];
    const width = bounds.maxX - bounds.minX;
    const depth = bounds.maxZ - bounds.minZ;
    const cx = (bounds.minX + bounds.maxX) / 2;
    const cz = (bounds.minZ + bounds.maxZ) / 2;

    const grass = kit.grass.clone();
    grass.repeat.set(80, 80);
    grass.needsUpdate = true;
    const ground = mesh(
        new THREE.PlaneGeometry(320, 320),
        new THREE.MeshStandardMaterial({ map: grass, roughness: 0.95 }),
        [cx, 0, cz],
    );
    ground.rotation.x = -Math.PI / 2;
    ground.castShadow = false;
    group.add(ground);

    // Trees round the lawn, a few metres out.
    const trunk = new THREE.MeshStandardMaterial({
        color: '#6b4a2f',
        roughness: 0.9,
    });
    const leaves = ['#4f7f3a', '#5f8f42', '#3f6d34'].map(
        (color) =>
            new THREE.MeshStandardMaterial({
                color,
                roughness: 0.85,
                flatShading: true,
            }),
    );
    const crown = new THREE.IcosahedronGeometry(1, 0);
    const stem = new THREE.CylinderGeometry(0.14, 0.22, 2.4, 7);
    const perimeter = 2 * (width + depth) + 40;
    const trees = Math.round(perimeter / 4.5);

    for (let index = 0; index < trees; index++) {
        const t = index / trees;
        const margin = 5 + ((index * 53) % 7);
        const w = width + margin * 2;
        const d = depth + margin * 2;
        const along = t * 2 * (w + d);
        let x: number;
        let z: number;

        if (along < w) {
            [x, z] = [bounds.minX - margin + along, bounds.minZ - margin];
        } else if (along < w + d) {
            [x, z] = [bounds.maxX + margin, bounds.minZ - margin + along - w];
        } else if (along < 2 * w + d) {
            [x, z] = [
                bounds.maxX + margin - (along - w - d),
                bounds.maxZ + margin,
            ];
        } else {
            [x, z] = [
                bounds.minX - margin,
                bounds.maxZ + margin - (along - 2 * w - d),
            ];
        }

        const tree = new THREE.Group();
        tree.position.set(
            x + ((index * 31) % 5) - 2,
            0,
            z + ((index * 17) % 5) - 2,
        );
        const scale = 0.8 + ((index * 29) % 10) / 15;
        tree.scale.setScalar(scale);
        tree.add(mesh(stem, trunk, [0, 1.2, 0]));
        const foliage = new THREE.Group();
        foliage.position.y = 2.4;
        foliage.add(mesh(crown, leaves[index % 3], [0, 0.6, 0]).rotateY(index));
        foliage.add(
            mesh(crown, leaves[(index + 1) % 3], [0.55, 0.2, 0.2]).rotateY(
                index * 2,
            ),
        );
        foliage.add(
            mesh(crown, leaves[(index + 2) % 3], [-0.45, 0.3, -0.3]).rotateX(
                index,
            ),
        );
        foliage.children.forEach((child, part) =>
            child.scale.setScalar(1.3 - part * 0.2),
        );
        tree.add(foliage);
        const phase = index * 0.7;

        if (!reducedMotion) {
            ticks.push((time) => {
                foliage.rotation.z = Math.sin(time * 0.8 + phase) * 0.03;
                foliage.rotation.x = Math.cos(time * 0.6 + phase) * 0.02;
            });
        }

        group.add(tree);
        decor.push(tree);
    }

    // String lights: poles along the long sides, strands strung across.
    const pole = new THREE.CylinderGeometry(0.06, 0.08, 4.6, 8);
    const spans = Math.max(2, Math.round(width / 6) + 1);
    const tops: THREE.Vector3[][] = [[], []];

    for (let index = 0; index < spans; index++) {
        const x = bounds.minX + (index / (spans - 1)) * width;

        [bounds.minZ - 0.6, bounds.maxZ + 0.6].forEach((z, side) => {
            const post = mesh(pole, kit.darkWood, [x, 2.3, z]);
            group.add(post);
            decor.push(post);
            tops[side].push(new THREE.Vector3(x, 4.5, z));
        });
    }

    const bulbs: THREE.Vector3[] = [];
    const wire: THREE.Vector3[][] = [];

    for (let index = 0; index < spans; index++) {
        const [from, to] = [tops[0][index], tops[1][index]];
        const length = from.distanceTo(to);
        const count = Math.round(length / 0.7);
        const strand: THREE.Vector3[] = [];

        for (let step = 0; step <= count; step++) {
            const t = step / count;
            const point = from.clone().lerp(to, t);
            point.y -= Math.sin(t * Math.PI) * Math.min(1.4, length * 0.06);
            strand.push(point);

            if (step > 0 && step < count) {
                bulbs.push(point.clone().add(new THREE.Vector3(0, -0.06, 0)));
            }
        }

        wire.push(strand);
    }

    const wireMaterial = new THREE.LineBasicMaterial({ color: '#2a2622' });
    wire.forEach((strand) =>
        group.add(
            new THREE.Line(
                new THREE.BufferGeometry().setFromPoints(strand),
                wireMaterial,
            ),
        ),
    );

    const bulbMaterial = new THREE.MeshBasicMaterial({ color: '#ffe1a8' });
    const bulbMesh = new THREE.InstancedMesh(
        kit.bloom,
        bulbMaterial,
        bulbs.length,
    );
    const matrix = new THREE.Matrix4();
    bulbs.forEach((point, index) =>
        bulbMesh.setMatrixAt(
            index,
            matrix.makeScale(0.045, 0.06, 0.045).setPosition(point),
        ),
    );
    group.add(bulbMesh);

    const glows = new THREE.Points(
        new THREE.BufferGeometry().setFromPoints(bulbs),
        new THREE.PointsMaterial({
            map: kit.glow,
            color: '#ffc877',
            size: 0.28,
            transparent: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending,
        }),
    );
    group.add(glows);
    ticks.push(
        (time) =>
            ((glows.material as THREE.PointsMaterial).opacity =
                0.75 + Math.sin(time * 1.7) * 0.15),
    );

    // Petals drifting down over the tables.
    if (!reducedMotion) {
        const count = 160;
        const positions = new Float32Array(count * 3);
        const reset = (index: number, top: boolean) => {
            positions[index * 3] = bounds.minX + Math.random() * width;
            positions[index * 3 + 1] = top
                ? 6 + Math.random() * 2
                : Math.random() * 8;
            positions[index * 3 + 2] = bounds.minZ + Math.random() * depth;
        };

        for (let index = 0; index < count; index++) {
            reset(index, false);
        }

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute(
            'position',
            new THREE.BufferAttribute(positions, 3),
        );
        const petals = new THREE.Points(
            geometry,
            new THREE.PointsMaterial({
                color: '#f6b8c6',
                size: 0.09,
                transparent: true,
                opacity: 0.9,
                depthWrite: false,
            }),
        );
        group.add(petals);
        let last = 0;
        ticks.push((time) => {
            const delta = Math.min(0.05, time - last);
            last = time;

            for (let index = 0; index < count; index++) {
                positions[index * 3] += Math.sin(time + index) * 0.004;
                positions[index * 3 + 1] -= delta * (0.35 + (index % 5) * 0.06);
                positions[index * 3 + 2] +=
                    Math.cos(time * 0.7 + index) * 0.004;

                if (positions[index * 3 + 1] < 0) {
                    reset(index, true);
                }
            }

            geometry.attributes.position.needsUpdate = true;
        });
    }

    group.add(new THREE.HemisphereLight('#d6e6ff', '#58743a', 1.35));
    const sun = sunLight(
        bounds,
        '#ffd9a8',
        2.6,
        new THREE.Vector3(-18, 26, 14),
    );
    group.add(sun, sun.target);

    return {
        group,
        ticks,
        decor,
        background: skyTexture('#86aedb', '#f8dcb6'),
        fog: new THREE.Fog('#f1d9bb', 60, 170),
        exposure: 1.05,
        sun,
        cutaway: [],
    };
}
