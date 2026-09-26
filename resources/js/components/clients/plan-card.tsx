import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { planSummary } from '@/lib/plans';
import { plan as updatePlan } from '@/routes/admin/clients';
import type {
    Client,
    ClientPlanKey,
    ClientPlanUsage,
    Option,
    StaffActivity,
} from '@/types';

const subscriptions: ClientPlanKey[] = ['business', 'enterprise'];

/**
 * A client's plan in the admin console. Staff with `clients.plan` change
 * it after payment; every change needs a note and is logged with who made
 * it (the history below, for staff who can read the activity log).
 */
export function PlanCard({
    client,
    usage,
    plans,
    history,
    canEdit,
}: {
    client: Client;
    usage: ClientPlanUsage;
    plans: Option[];
    history: StaffActivity[] | null;
    canEdit: boolean;
}) {
    const form = useForm({
        plan: usage.plan as ClientPlanKey,
        plan_expires_at: usage.expires_at ? usage.expires_at.slice(0, 10) : '',
        event_credits: usage.event_credits,
        note: '',
    });
    const isSubscription = subscriptions.includes(form.data.plan);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(updatePlan.url(client), {
            preserveScroll: true,
            onSuccess: () => form.reset('note'),
        });
    };

    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Plan</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="text-sm">
                    <p className="font-medium">{planSummary(usage)}</p>
                    <p className="text-xs text-muted-foreground">
                        {usage.events_used}{' '}
                        {usage.events_used === 1 ? 'event' : 'events'} created ·{' '}
                        {usage.max_guests_per_event === null
                            ? 'unlimited guests'
                            : `${usage.max_guests_per_event} guests per event`}{' '}
                        · {usage.message_limits.invitations} invitations +{' '}
                        {usage.message_limits.reminders} reminders per guest
                    </p>
                    {usage.reason && (
                        <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
                            {usage.reason}
                        </p>
                    )}
                </div>

                {canEdit && (
                    <form className="space-y-3 border-t pt-4" onSubmit={submit}>
                        <FormField
                            label="Plan"
                            htmlFor="plan"
                            error={form.errors.plan}
                        >
                            <Select
                                id="plan"
                                value={form.data.plan}
                                onChange={(e) =>
                                    form.setData(
                                        'plan',
                                        e.target.value as ClientPlanKey,
                                    )
                                }
                            >
                                {plans.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                        {isSubscription && (
                            <FormField
                                label="Active until"
                                htmlFor="plan_expires_at"
                                error={form.errors.plan_expires_at}
                                hint="Leave empty for no end date."
                            >
                                <Input
                                    id="plan_expires_at"
                                    type="date"
                                    value={form.data.plan_expires_at}
                                    onChange={(e) =>
                                        form.setData(
                                            'plan_expires_at',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        )}
                        {form.data.plan === 'celebration' && (
                            <FormField
                                label="Event credits"
                                htmlFor="event_credits"
                                error={form.errors.event_credits}
                                hint="Paid events not created yet; each new event uses one."
                            >
                                <Input
                                    id="event_credits"
                                    type="number"
                                    min={0}
                                    max={1000}
                                    value={form.data.event_credits}
                                    onChange={(e) =>
                                        form.setData(
                                            'event_credits',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </FormField>
                        )}
                        <FormField
                            label="Note"
                            htmlFor="plan_note"
                            error={form.errors.note}
                            hint="Required: why it changed, e.g. the payment reference."
                        >
                            <Textarea
                                id="plan_note"
                                rows={2}
                                maxLength={500}
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                            />
                        </FormField>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={form.processing}
                        >
                            Save plan
                        </Button>
                    </form>
                )}

                {history && (
                    <div className="border-t pt-4">
                        <p className="mb-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Plan history
                        </p>
                        {history.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                No changes yet.
                            </p>
                        ) : (
                            <ol className="space-y-2.5">
                                {history.map((entry) => (
                                    <li key={entry.id} className="text-xs">
                                        <p className="font-medium text-foreground">
                                            {entry.description}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {entry.staff?.name ??
                                                'Deleted staff member'}{' '}
                                            · {formatDateTime(entry.created_at)}
                                        </p>
                                        {entry.note && (
                                            <p className="mt-0.5 rounded bg-muted/60 px-1.5 py-1">
                                                {entry.note}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
