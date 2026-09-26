import { Check, X } from 'lucide-react';

import { useHasPlanFeature } from '@/lib/plans';
import { cn } from '@/lib/utils';
import type { RegistrationType, RegistrationTypeOption } from '@/types';

/** What a registration type means, as a ✓ / ✗ checklist. */
export function RegistrationTypeDetails({
    option,
    className,
}: {
    option: RegistrationTypeOption;
    className?: string;
}) {
    return (
        <ul className={cn('space-y-1.5 text-xs', className)}>
            {option.details.map((detail) => (
                <li key={detail.label} className="flex gap-1.5">
                    {detail.ok ? (
                        <Check className="mt-px size-3.5 shrink-0 text-emerald-600" />
                    ) : (
                        <X className="mt-px size-3.5 shrink-0 text-muted-foreground" />
                    )}
                    <span>
                        <span className="text-muted-foreground">
                            {detail.label}:
                        </span>{' '}
                        {detail.value}
                    </span>
                </li>
            ))}
        </ul>
    );
}

type RegistrationTypePickerProps = {
    options: RegistrationTypeOption[];
    value: RegistrationType;
    onChange: (value: RegistrationType) => void;
    error?: string;
};

/** Three selectable cards (open, approval required, guest list only) that spell out what each means. */
export function RegistrationTypePicker({
    options,
    value,
    onChange,
    error,
}: RegistrationTypePickerProps) {
    // Starter has no public registration: only guest list only can be picked.
    const hasFeature = useHasPlanFeature();
    const allowed = (type: RegistrationType) =>
        type === 'guest_list_only' || hasFeature('public_registration');

    return (
        <div className="space-y-2">
            <div
                role="radiogroup"
                aria-label="Who can join"
                className="grid gap-3 md:grid-cols-3"
            >
                {options.map((option) => {
                    const selected = option.value === value;
                    const available = allowed(option.value);

                    return (
                        <label
                            key={option.value}
                            className={cn(
                                'flex flex-col gap-2 rounded-lg border p-3 transition-colors',
                                available
                                    ? 'cursor-pointer'
                                    : 'cursor-not-allowed opacity-60',
                                selected
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                    : 'border-border hover:bg-muted/50',
                            )}
                            title={
                                available
                                    ? undefined
                                    : 'Public registration is available from the Celebration plan'
                            }
                        >
                            <span className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="registration_type"
                                    value={option.value}
                                    checked={selected}
                                    disabled={!available}
                                    onChange={() => onChange(option.value)}
                                    className="accent-primary"
                                />
                                <span className="text-sm font-medium">
                                    {option.label}
                                </span>
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {option.description}
                            </span>
                            {!available && (
                                <span className="text-xs font-medium text-amber-700 dark:text-amber-400">
                                    From the Celebration plan
                                </span>
                            )}
                            <RegistrationTypeDetails option={option} />
                        </label>
                    );
                })}
            </div>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
