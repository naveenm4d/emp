import { useForm } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

import { FormField } from '@/components/shared/form-field';
import { MessagePlaceholders } from '@/components/shared/message-placeholders';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { Guest } from '@/types';

export type GuestFormData = {
    name: string;
    email: string;
    phone: string;
    notes: string;
    invitation_message: string;
    reminder_message: string;
    invited_additional_guests: number | '';
    invited_children: number | '';
};

type GuestFormProps = {
    guest?: Guest;
    /** The event's invitation message, shown when the guest has no custom one. */
    defaultMessage?: string;
    /** The event's reminder message, shown when the guest has no custom one. */
    defaultReminderMessage?: string;
    submitLabel: string;
    onSubmit: (form: ReturnType<typeof useForm<GuestFormData>>) => void;
    onCancel?: () => void;
};

export function GuestForm({
    guest,
    defaultMessage,
    defaultReminderMessage,
    submitLabel,
    onSubmit,
    onCancel,
}: GuestFormProps) {
    const form = useForm<GuestFormData>({
        name: guest?.name ?? '',
        email: guest?.email ?? '',
        phone: guest?.phone ?? '',
        notes: guest?.notes ?? '',
        invitation_message: guest?.invitation_message ?? '',
        reminder_message: guest?.reminder_message ?? '',
        invited_additional_guests: guest?.invited_additional_guests ?? '',
        invited_children: guest?.invited_children ?? '',
    });
    const [showsMessages, setShowsMessages] = useState(
        !!guest?.invitation_message || !!guest?.reminder_message,
    );
    const id = guest?.id ?? 'new';

    /** The event's text as this guest would get it (their name filled in). */
    const forGuest = (text?: string) =>
        text?.replace(
            /\{\{\s*guest\.name\s*\}\}/g,
            form.data.name || 'guest name',
        );

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
                hint="Add an email or a phone, so you can reach them."
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
            <div className="grid grid-cols-2 gap-3">
                <FormField
                    label="Plus-ones"
                    htmlFor={`plus-ones-${id}`}
                    error={form.errors.invited_additional_guests}
                >
                    <Input
                        id={`plus-ones-${id}`}
                        type="number"
                        min={0}
                        max={20}
                        placeholder="Default"
                        title="How many people the guest is invited with. Leave empty to use the event's settings."
                        value={form.data.invited_additional_guests}
                        onChange={(e) =>
                            form.setData(
                                'invited_additional_guests',
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                    />
                </FormField>
                <FormField
                    label="Children"
                    htmlFor={`children-${id}`}
                    error={form.errors.invited_children}
                >
                    <Input
                        id={`children-${id}`}
                        type="number"
                        min={0}
                        max={20}
                        placeholder="Default"
                        title="How many children the guest is invited with. Leave empty to use the event's settings."
                        value={form.data.invited_children}
                        onChange={(e) =>
                            form.setData(
                                'invited_children',
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                    />
                </FormField>
            </div>
            <div className="sm:col-span-2 lg:col-span-4">
                <button
                    type="button"
                    className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
                    aria-expanded={showsMessages}
                    onClick={() => setShowsMessages((shown) => !shown)}
                >
                    <ChevronRight
                        className={cn(
                            'size-3.5 transition-transform',
                            showsMessages && 'rotate-90',
                        )}
                    />
                    Custom messages
                    {(form.data.invitation_message ||
                        form.data.reminder_message) &&
                        !showsMessages &&
                        ' (set)'}
                </button>
                {showsMessages && (
                    <div className="mt-3 grid gap-4 lg:grid-cols-2">
                        <FormField
                            label="Invitation (optional)"
                            htmlFor={`invitation_message-${id}`}
                            error={form.errors.invitation_message}
                            hint="Leave empty to send the event's invitation."
                        >
                            <Textarea
                                id={`invitation_message-${id}`}
                                rows={2}
                                placeholder={forGuest(defaultMessage)}
                                value={form.data.invitation_message}
                                onChange={(e) =>
                                    form.setData(
                                        'invitation_message',
                                        e.target.value,
                                    )
                                }
                            />
                            <MessagePlaceholders
                                message={form.data.invitation_message}
                            />
                        </FormField>
                        <FormField
                            label="Reminder (optional)"
                            htmlFor={`reminder_message-${id}`}
                            error={form.errors.reminder_message}
                            hint="Leave empty to send the event's reminder."
                        >
                            <Textarea
                                id={`reminder_message-${id}`}
                                rows={2}
                                placeholder={forGuest(defaultReminderMessage)}
                                value={form.data.reminder_message}
                                onChange={(e) =>
                                    form.setData(
                                        'reminder_message',
                                        e.target.value,
                                    )
                                }
                            />
                            <MessagePlaceholders
                                message={form.data.reminder_message}
                            />
                        </FormField>
                    </div>
                )}
            </div>
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
