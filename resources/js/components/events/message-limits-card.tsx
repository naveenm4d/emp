import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { messageLimits as updateLimits } from '@/routes/admin/clients/events';
import type { Event } from '@/types';

/**
 * Staff only: how many WhatsApp invitations and reminders each guest of the
 * event can get. Empty uses the platform default. Failed messages don't count.
 */
export function MessageLimitsCard({
    event,
    clientId,
}: {
    event: Event;
    clientId: string;
}) {
    const limits = event.message_limits;
    const form = useForm<{
        max_invitations_per_guest: number | '';
        max_reminders_per_guest: number | '';
    }>({
        max_invitations_per_guest: limits.invitations_override ?? '',
        max_reminders_per_guest: limits.reminders_override ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(updateLimits.url({ client: clientId, event: event.id }), {
            preserveScroll: true,
        });
    };

    const number = (value: string): number | '' =>
        value === '' ? '' : Number(value);

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle>Message limits</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={submit}
                    className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                >
                    <FormField
                        label="Invitations per guest"
                        htmlFor="max_invitations_per_guest"
                        error={form.errors.max_invitations_per_guest}
                        hint="Send, resend and re-invite with a new link."
                    >
                        <Input
                            id="max_invitations_per_guest"
                            type="number"
                            min={1}
                            max={20}
                            placeholder={`Default: ${limits.default_invitations}`}
                            value={form.data.max_invitations_per_guest}
                            onChange={(e) =>
                                form.setData(
                                    'max_invitations_per_guest',
                                    number(e.target.value),
                                )
                            }
                        />
                    </FormField>
                    <FormField
                        label="Reminders per guest"
                        htmlFor="max_reminders_per_guest"
                        error={form.errors.max_reminders_per_guest}
                        hint="Manual and automatic reminders."
                    >
                        <Input
                            id="max_reminders_per_guest"
                            type="number"
                            min={0}
                            max={20}
                            placeholder={`Default: ${limits.default_reminders}`}
                            value={form.data.max_reminders_per_guest}
                            onChange={(e) =>
                                form.setData(
                                    'max_reminders_per_guest',
                                    number(e.target.value),
                                )
                            }
                        />
                    </FormField>
                    <Button type="submit" disabled={form.processing}>
                        Save limits
                    </Button>
                </form>
                <p className="mt-3 text-xs text-muted-foreground">
                    Now: {limits.invitations} invitations and {limits.reminders}{' '}
                    reminders per guest. Leave empty for the platform default.
                    Failed messages don't count.
                </p>
            </CardContent>
        </Card>
    );
}
