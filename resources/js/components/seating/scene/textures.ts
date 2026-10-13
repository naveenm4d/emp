import * as THREE from 'three';

/**
 * Textures drawn on a canvas at runtime, so the 3D floor plan needs no image
 * files: wooden floor, lawn, dance floor tiles and the sky.
 */

function canvasTexture(
    width: number,
    height: number,
    draw: (context: CanvasRenderingContext2D) => void,
): THREE.CanvasTexture {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    draw(canvas.getContext('2d')!);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;

    return texture;
}

/** A small seeded random, so textures look the same on every load. */
function random(seed: number) {
    let state = seed;

    return () => {
        state = (state * 16807) % 2147483647;

        return (state - 1) / 2147483646;
    };
}

/** Warm oak planks, staggered, for the hall floor (one tile ≈ 2 m). */
export function woodFloorTexture(): THREE.CanvasTexture {
    const next = random(7);
    const texture = canvasTexture(512, 512, (context) => {
        const rows = 8;
        const plank = 512 / rows;

        for (let row = 0; row < rows; row++) {
            const offset = (row % 2) * 128 + next() * 64;

            for (let x = -offset; x < 512; x += 256) {
                const tone = 150 + next() * 40;
                context.fillStyle = `rgb(${tone}, ${tone * 0.68}, ${tone * 0.42})`;
                context.fillRect(x, row * plank, 256, plank);

                // Grain.
                for (let line = 0; line < 6; line++) {
                    context.strokeStyle = `rgba(70, 40, 20, ${0.06 + next() * 0.08})`;
                    context.lineWidth = 1 + next();
                    context.beginPath();
                    const y = row * plank + next() * plank;
                    context.moveTo(x, y);
                    context.bezierCurveTo(
                        x + 80,
                        y + (next() - 0.5) * 8,
                        x + 170,
                        y + (next() - 0.5) * 8,
                        x + 256,
                        y,
                    );
                    context.stroke();
                }

                context.fillStyle = 'rgba(40, 22, 10, 0.55)';
                context.fillRect(x, row * plank, 2, plank);
            }

            context.fillStyle = 'rgba(40, 22, 10, 0.45)';
            context.fillRect(0, row * plank, 512, 1.5);
        }
    });
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;

    return texture;
}

/** Mown lawn with soft stripes and speckles (one tile ≈ 4 m). */
export function grassTexture(): THREE.CanvasTexture {
    const next = random(11);
    const texture = canvasTexture(512, 512, (context) => {
        context.fillStyle = '#5b8a3c';
        context.fillRect(0, 0, 512, 512);

        // Mowing stripes.
        for (let stripe = 0; stripe < 4; stripe++) {
            context.fillStyle =
                stripe % 2 ? 'rgba(255,255,220,0.05)' : 'rgba(0,30,0,0.05)';
            context.fillRect(stripe * 128, 0, 128, 512);
        }

        for (let blade = 0; blade < 9000; blade++) {
            const light = next();
            context.fillStyle =
                light > 0.5
                    ? `rgba(160, 200, 100, ${0.15 + next() * 0.25})`
                    : `rgba(30, 70, 20, ${0.15 + next() * 0.3})`;
            context.fillRect(next() * 512, next() * 512, 1.5, 3 + next() * 3);
        }
    });
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;

    return texture;
}

/** Glossy black and ivory tiles with a glow, for the dance floor. */
export function danceFloorTexture(): THREE.CanvasTexture {
    const texture = canvasTexture(256, 256, (context) => {
        const tile = 64;

        for (let x = 0; x < 4; x++) {
            for (let y = 0; y < 4; y++) {
                const light = (x + y) % 2 === 0;
                const gradient = context.createRadialGradient(
                    x * tile + tile / 2,
                    y * tile + tile / 2,
                    4,
                    x * tile + tile / 2,
                    y * tile + tile / 2,
                    tile * 0.75,
                );
                gradient.addColorStop(0, light ? '#fff6e0' : '#2a2433');
                gradient.addColorStop(1, light ? '#d9c8a4' : '#120f18');
                context.fillStyle = gradient;
                context.fillRect(x * tile, y * tile, tile, tile);
                context.strokeStyle = 'rgba(217, 194, 154, 0.6)';
                context.strokeRect(x * tile + 0.5, y * tile + 0.5, tile, tile);
            }
        }
    });
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;

    return texture;
}

/** A vertical sky gradient (top colour to horizon colour). */
export function skyTexture(top: string, horizon: string): THREE.CanvasTexture {
    return canvasTexture(4, 256, (context) => {
        const gradient = context.createLinearGradient(0, 0, 0, 256);
        gradient.addColorStop(0, top);
        gradient.addColorStop(0.75, horizon);
        gradient.addColorStop(1, horizon);
        context.fillStyle = gradient;
        context.fillRect(0, 0, 4, 256);
    });
}

/** A soft round glow (for flames, bulbs and petals as sprites). */
export function glowTexture(): THREE.CanvasTexture {
    return canvasTexture(64, 64, (context) => {
        const gradient = context.createRadialGradient(32, 32, 0, 32, 32, 32);
        gradient.addColorStop(0, 'rgba(255,255,255,1)');
        gradient.addColorStop(0.35, 'rgba(255,255,255,0.55)');
        gradient.addColorStop(1, 'rgba(255,255,255,0)');
        context.fillStyle = gradient;
        context.fillRect(0, 0, 64, 64);
    });
}
