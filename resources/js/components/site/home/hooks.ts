import type { RefObject } from 'react';
import { useEffect, useState, useSyncExternalStore } from 'react';

const reducedMotionQuery = '(prefers-reduced-motion: reduce)';

function subscribeReducedMotion(callback: () => void) {
    const media = window.matchMedia(reducedMotionQuery);
    media.addEventListener('change', callback);

    return () => media.removeEventListener('change', callback);
}

export function usePrefersReducedMotion(): boolean {
    return useSyncExternalStore(
        subscribeReducedMotion,
        () => window.matchMedia(reducedMotionQuery).matches,
        () => false,
    );
}

/** True once the element has scrolled into view (and stays true when `once`). */
export function useInView(
    ref: RefObject<Element | null>,
    { threshold = 0.2, once = true, rootMargin = '0px' } = {},
): boolean {
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setInView(true);
                    if (once) {
                        observer.disconnect();
                    }
                } else if (!once) {
                    setInView(false);
                }
            },
            { threshold, rootMargin },
        );
        observer.observe(el);

        return () => observer.disconnect();
    }, [ref, threshold, once, rootMargin]);

    return inView;
}

/**
 * Scroll progress through a tall section: 0 when its top reaches the top of
 * the viewport, 1 when its bottom reaches the bottom of the viewport.
 */
export function useScrollProgress(ref: RefObject<Element | null>): number {
    const [progress, setProgress] = useState(0);

    useEffect(() => {
        let frame = 0;

        const update = () => {
            frame = 0;
            const el = ref.current;
            if (!el) {
                return;
            }
            const rect = el.getBoundingClientRect();
            const distance = rect.height - window.innerHeight;
            const value = distance > 0 ? -rect.top / distance : 0;
            setProgress(Math.min(1, Math.max(0, value)));
        };

        const onScroll = () => {
            if (!frame) {
                frame = requestAnimationFrame(update);
            }
        };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);

        return () => {
            cancelAnimationFrame(frame);
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, [ref]);

    return progress;
}

/** Page scroll offset in px, rAF-throttled. */
export function useScrollY(): number {
    const [y, setY] = useState(0);

    useEffect(() => {
        let frame = 0;
        const onScroll = () => {
            if (!frame) {
                frame = requestAnimationFrame(() => {
                    frame = 0;
                    setY(window.scrollY);
                });
            }
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            cancelAnimationFrame(frame);
            window.removeEventListener('scroll', onScroll);
        };
    }, []);

    return y;
}

/** Counts from 0 to `target` with an ease-out once `start` is true. */
export function useCountUp(
    target: number,
    start: boolean,
    duration = 1600,
): number {
    const [value, setValue] = useState(0);

    useEffect(() => {
        if (!start) {
            return;
        }

        let frame = 0;
        const began = performance.now();
        const tick = (now: number) => {
            const t = Math.min(1, (now - began) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            setValue(Math.round(target * eased));
            if (t < 1) {
                frame = requestAnimationFrame(tick);
            }
        };
        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [target, start, duration]);

    return value;
}

/** Cycles 0..count-1 every `interval` ms while `active`. */
export function useTicker(count: number, interval: number, active = true) {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (!active) {
            return;
        }
        const id = window.setInterval(
            () => setIndex((i) => (i + 1) % count),
            interval,
        );

        return () => window.clearInterval(id);
    }, [count, interval, active]);

    return index;
}
