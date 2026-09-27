import type { Appearance } from '@/lib/appearance';
import { useAppearance } from '@/lib/appearance';
import { cn } from '@/lib/utils';

/** Preview swatches: page, surface and accent of each theme. */
const LIGHT = { page: '#EEF0F2', surface: '#FFFFFF', accent: '#D97757' };
const DARK = { page: '#141413', surface: '#1F1E1D', accent: '#D97757' };

const OPTIONS: {
    value: Appearance;
    label: string;
    halves: [typeof LIGHT, typeof LIGHT];
}[] = [
    { value: 'light', label: 'Light', halves: [LIGHT, LIGHT] },
    { value: 'dark', label: 'Dark', halves: [DARK, DARK] },
    { value: 'system', label: 'System', halves: [LIGHT, DARK] },
];

/** Theme choice with previews (account page). */
export function AppearancePicker() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="radiogroup"
            aria-label="Theme"
            className="grid grid-cols-3 gap-2.5"
        >
            {OPTIONS.map((option) => {
                const checked = option.value === appearance;

                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={checked}
                        onClick={() => updateAppearance(option.value)}
                        className="group flex flex-col items-center gap-2 rounded-lg outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
                    >
                        <span
                            className={cn(
                                'flex h-28 w-full rounded-xl p-1 transition-colors',
                                checked
                                    ? 'ring-2 ring-strong dark:ring-primary'
                                    : 'ring-1 ring-border group-hover:ring-foreground/30',
                            )}
                        >
                            <span className="flex flex-1 overflow-hidden rounded-lg">
                                {option.halves.map((half, index) => (
                                    <span
                                        key={index}
                                        className="flex flex-1 flex-col gap-1.25 px-1.5 py-2"
                                        style={{ background: half.page }}
                                    >
                                        <span
                                            className="h-3.5 rounded"
                                            style={{ background: half.surface }}
                                        />
                                        <span
                                            className="h-6 rounded-[5px]"
                                            style={{ background: half.surface }}
                                        />
                                        <span
                                            className="h-1.5 w-3/5 rounded-full"
                                            style={{ background: half.accent }}
                                        />
                                        <span
                                            className="h-6 rounded-[5px]"
                                            style={{ background: half.surface }}
                                        />
                                    </span>
                                ))}
                            </span>
                        </span>
                        <span className="inline-flex items-center gap-1.5 text-[13px] font-semibold">
                            <span
                                className={cn(
                                    'inline-flex size-4 items-center justify-center rounded-full',
                                    checked
                                        ? 'border-2 border-strong dark:border-primary'
                                        : 'border-[1.5px] border-subtle',
                                )}
                            >
                                {checked && (
                                    <span className="size-2 rounded-full bg-strong dark:bg-primary" />
                                )}
                            </span>
                            {option.label}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
