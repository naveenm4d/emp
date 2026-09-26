import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { store } from '@/routes/client/events/tables';
import { update } from '@/routes/client/tables';
import type { Event, EventTable, Option, TableShape } from '@/types';

/** Add a table, or rename / resize one (the key re-mounts it per table). */
export function TableFormDialog({
    open,
    onOpenChange,
    event,
    table,
    suggestedName,
    shapes,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    event: Event;
    /** The table to edit; leave out to add one. */
    table?: EventTable | null;
    suggestedName?: string;
    shapes: Option[];
}) {
    const form = useForm({
        name: table?.name ?? suggestedName ?? '',
        seat_count: table?.seat_count ?? 8,
        shape: table?.shape ?? 'round',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        };

        if (table) {
            form.patch(update.url(table), options);
        } else {
            form.post(store.url(event), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle>
                        {table ? `Edit ${table.name}` : 'Add a table'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4 p-4">
                    <FormField
                        label="Name"
                        htmlFor="table-name"
                        error={form.errors.name}
                        hint="e.g. Table 1, A or Head table"
                    >
                        <Input
                            id="table-name"
                            autoFocus
                            maxLength={60}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="grid grid-cols-2 gap-3">
                        <FormField
                            label="Seats"
                            htmlFor="table-seats"
                            error={form.errors.seat_count}
                        >
                            <Input
                                id="table-seats"
                                type="number"
                                min={1}
                                max={50}
                                value={form.data.seat_count}
                                onChange={(e) =>
                                    form.setData(
                                        'seat_count',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            label="Shape"
                            htmlFor="table-shape"
                            error={form.errors.shape}
                        >
                            <Select
                                id="table-shape"
                                value={form.data.shape}
                                onChange={(e) =>
                                    form.setData(
                                        'shape',
                                        e.target.value as TableShape,
                                    )
                                }
                            >
                                {shapes.map((shape) => (
                                    <option
                                        key={shape.value}
                                        value={shape.value}
                                    >
                                        {shape.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {table ? 'Save' : 'Add table'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
