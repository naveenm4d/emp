import { useCallback, useSyncExternalStore } from 'react';

/**
 * Dashboard theme (client app and staff console). The choice is kept in
 * the `appearance` cookie so the server renders the right class on the
 * first paint (see HandleAppearance), with localStorage as a fallback.
 * Guest-facing pages don't take part: only root views that carry
 * `data-appearance` are themed.
 */
export type Appearance = 'light' | 'dark' | 'system';

export const APPEARANCES: Appearance[] = ['light', 'dark', 'system'];

const STORAGE_KEY = 'appearance';
const DEFAULT_APPEARANCE: Appearance = 'light';
const listeners = new Set<() => void>();

function isAppearance(value: unknown): value is Appearance {
    return APPEARANCES.includes(value as Appearance);
}

function isThemedPage(): boolean {
    return (
        typeof document !== 'undefined' &&
        document.documentElement.hasAttribute('data-appearance')
    );
}

function prefersDark(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-color-scheme: dark)').matches
    );
}

function readAppearance(): Appearance {
    if (typeof document === 'undefined') {
        return DEFAULT_APPEARANCE;
    }

    const fromServer = document.documentElement.dataset.appearance;

    if (isAppearance(fromServer)) {
        return fromServer;
    }

    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return isAppearance(stored) ? stored : DEFAULT_APPEARANCE;
    } catch {
        return DEFAULT_APPEARANCE;
    }
}

function applyTheme(appearance: Appearance): void {
    if (!isThemedPage()) {
        return;
    }

    const dark =
        appearance === 'dark' || (appearance === 'system' && prefersDark());

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.appearance = appearance;
}

function persist(appearance: Appearance): void {
    const maxAge = 365 * 24 * 60 * 60;

    document.cookie = `${STORAGE_KEY}=${appearance};path=/;max-age=${maxAge};SameSite=Lax`;

    try {
        localStorage.setItem(STORAGE_KEY, appearance);
    } catch {
        // Private mode or blocked storage: the cookie is enough.
    }
}

/** Applies the saved theme and follows the device while on "system". */
export function initializeTheme(): void {
    if (!isThemedPage()) {
        return;
    }

    applyTheme(readAppearance());

    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (readAppearance() === 'system') {
                applyTheme('system');
            }
        });
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export function useAppearance(): {
    appearance: Appearance;
    updateAppearance: (appearance: Appearance) => void;
} {
    const appearance = useSyncExternalStore(
        subscribe,
        readAppearance,
        () => DEFAULT_APPEARANCE,
    );

    const updateAppearance = useCallback((next: Appearance) => {
        persist(next);
        applyTheme(next);
        listeners.forEach((listener) => listener());
    }, []);

    return { appearance, updateAppearance };
}
