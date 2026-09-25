import { useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Guest } from '@/types';

export type GuestFormData = {
    name: string;
    email: string;
    phone: string;
    notes: string;
};

type GuestFormProps = {
    guest?: Guest;
    submitLabel: string;
    onSubmit: (form: ReturnType<typeof useForm<GuestFormData>>) => void;
    onCancel?: () => void;
};

export function GuestForm({
    guest,
    submitLabel,
    onSubmit,
    onCancel,
}: GuestFormProps) {
    const form = useForm<GuestFormData>({
        name: guest?.name ?? '',
        email: guest?.email ?? '',
        phone: guest?.phone ?? '',
        notes: guest?.notes ?? '',
    });

    return (
        <form
            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit(form);
            }}
        >
            <FormField
                label="Name"
                htmlFor={`name-${guest?.id ?? 'new'}`}
                error={form.errors.name}
            >
                <Input
                    id={`name-${guest?.id ?? 'new'}`}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    autoFocus
                />
            </FormField>
            <FormField
                label="Email"
                htmlFor={`email-${guest?.id ?? 'new'}`}
                error={form.errors.email}
            >
                <Input
                    id={`email-${guest?.id ?? 'new'}`}
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                />
            </FormField>
            <FormField
                label="Phone (WhatsApp)"
                htmlFor={`phone-${guest?.id ?? 'new'}`}
                error={form.errors.phone}
            >
                <Input
                    id={`phone-${guest?.id ?? 'new'}`}
                    placeholder="+15550100200"
                    value={form.data.phone}
                    onChange={(e) => form.setData('phone', e.target.value)}
                />
            </FormField>
            <FormField
                label="Notes"
                htmlFor={`notes-${guest?.id ?? 'new'}`}
                error={form.errors.notes}
            >
                <Input
                    id={`notes-${guest?.id ?? 'new'}`}
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                />
            </FormField>
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {submitLabel}
                </Button>
                {onCancel && (
                    <Button type="button" variant="ghost" onClick={onCancel}>
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );
}
