import { Check, X } from 'lucide-react';

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
    return (
        <div className="space-y-2">
            <div
                role="radiogroup"
                aria-label="Who can join"
                className="grid gap-3 md:grid-cols-3"
            >
                {options.map((option) => {
                    const selected = option.value === value;

                    return (
                        <label
                            key={option.value}
                            className={cn(
                                'flex cursor-pointer flex-col gap-2 rounded-lg border p-3 transition-colors',
                                selected
                                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                    : 'border-border hover:bg-muted/50',
                            )}
                        >
                            <span className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="registration_type"
                                    value={option.value}
                                    checked={selected}
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
                            <RegistrationTypeDetails option={option} />
                        </label>
                    );
                })}
            </div>
            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
