import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCheck, ChevronDown, Clock } from 'lucide-react';
import { useState } from 'react';

import { channelIcons, statusStyles } from '@/components/messages/message-meta';
import { PageHeader } from '@/components/shared/page-header';
import EventLayout from '@/layouts/event-layout';
import { formatDateTime, initials } from '@/lib/format';
import { renderWhatsApp } from '@/lib/message-format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/client/events/notifications';
import type { Event, Notification, Resource } from '@/types';

/** One message: the text as the guest got it, where it got to, and the provider's log. */
export default function ShowNotification({
    event: { data: event },
    notification: { data: n },
}: {
    event: Resource<Event>;
    notification: Resource<Notification>;
}) {
    const style = statusStyles[n.status];
    const Channel = channelIcons[n.channel];
    const name = n.guest?.name ?? 'Removed guest';
    const failed = n.status === 'failed';

    const steps: { label: string; at: string | null; done: boolean }[] = [
        { label: 'Queued', at: n.created_at, done: true },
        { label: 'Sent', at: n.sent_at, done: !!n.sent_at },
        failed
            ? { label: 'Failed', at: n.updated_at, done: true }
            : {
                  label: 'Delivered',
                  at: n.delivered_at,
                  done: !!n.delivered_at,
              },
        ...(failed
            ? []
            : [{ label: 'Read', at: n.read_at, done: !!n.read_at }]),
    ];

    return (
        <EventLayout event={event}>
            <Head title={`Message to ${name}`} />
            <PageHeader
                eyebrow={
                    <Link
                        href={index.url(event)}
                        className="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <ArrowLeft className="size-3.5" /> Messages
                    </Link>
                }
                title={`${n.kind === 'rsvp_reminder' ? 'Reminder' : 'Invitation'} to ${name}`}
            />

            <div className="grid grid-cols-1 items-start gap-6 *:min-w-0 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section className="overflow-hidden rounded-2xl shadow-card">
                    <div className="flex items-center gap-3 bg-card px-4 py-3">
                        <span className="flex size-10 items-center justify-center rounded-full bg-foreground/6 text-sm font-bold">
                            {initials(name)}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-semibold">{name}</p>
                            <p className="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                <Channel className="size-3.5" />
                                {n.recipient}
                            </p>
                        </div>
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold',
                                style.tone,
                            )}
                        >
                            <style.icon className="size-3.5" />
                            {style.label}
                        </span>
                    </div>
                    <div className="min-h-56 bg-[#e7ddd3] bg-[radial-gradient(rgba(0,0,0,0.05)_1px,transparent_1px)] bg-size-[14px_14px] p-5 dark:bg-[#0b141a] dark:bg-[radial-gradient(rgba(255,255,255,0.04)_1px,transparent_1px)]">
                        <div className="relative ml-auto w-fit max-w-[88%] rounded-2xl rounded-tr-sm bg-[#d9fdd3] px-3.5 pt-2.5 pb-6 text-[14px] leading-snug text-[#111b21] shadow-sm dark:bg-[#005c4b] dark:text-[#e9edef]">
                            <div className="wrap-break-word">
                                {renderWhatsApp(n.message)}
                            </div>
                            <span className="absolute right-2.5 bottom-1.5 flex items-center gap-1 text-[10px] text-black/45 dark:text-white/60">
                                {n.sent_at
                                    ? new Date(n.sent_at).toLocaleTimeString(
                                          'en-GB',
                                          {
                                              hour: '2-digit',
                                              minute: '2-digit',
                                          },
                                      )
                                    : '—'}
                                {n.read_at ? (
                                    <CheckCheck className="size-3.5 text-sky-500" />
                                ) : n.delivered_at ? (
                                    <CheckCheck className="size-3.5" />
                                ) : (
                                    <Clock className="size-3" />
                                )}
                            </span>
                        </div>
                    </div>
                </section>

                <aside className="grid gap-4">
                    <section className="rounded-2xl bg-card p-5 shadow-card">
                        <h2 className="mb-4 text-sm font-bold">Delivery</h2>
                        <ol className="grid gap-0">
                            {steps.map((step, position) => {
                                const isFailure =
                                    failed && step.label === 'Failed';

                                return (
                                    <li
                                        key={step.label}
                                        className="relative flex gap-3 pb-5 last:pb-0"
                                    >
                                        {position < steps.length - 1 && (
                                            <span
                                                aria-hidden
                                                className={cn(
                                                    'absolute top-6 bottom-0 left-2.75 w-0.5',
                                                    steps[position + 1].done
                                                        ? 'bg-success'
                                                        : 'bg-foreground/10',
                                                )}
                                            />
                                        )}
                                        <span
                                            className={cn(
                                                'relative flex size-6 shrink-0 items-center justify-center rounded-full',
                                                isFailure
                                                    ? 'bg-destructive text-white'
                                                    : step.done
                                                      ? 'bg-success text-white'
                                                      : 'bg-foreground/8 text-subtle',
                                            )}
                                        >
                                            {step.done ? (
                                                <CheckCheck className="size-3.5" />
                                            ) : (
                                                <Clock className="size-3" />
                                            )}
                                        </span>
                                        <div className="min-w-0">
                                            <p
                                                className={cn(
                                                    'text-sm font-semibold',
                                                    !step.done && 'text-subtle',
                                                    isFailure &&
                                                        'text-destructive',
                                                )}
                                            >
                                                {step.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {step.done && step.at
                                                    ? formatDateTime(step.at)
                                                    : 'Not yet'}
                                            </p>
                                            {isFailure && n.error && (
                                                <p className="mt-1.5 rounded-lg bg-destructive-muted px-2.5 py-1.5 text-xs text-destructive">
                                                    {n.error}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                        <p className="mt-4 border-t border-border pt-3 text-xs text-muted-foreground">
                            {n.attempts}{' '}
                            {n.attempts === 1 ? 'attempt' : 'attempts'} ·{' '}
                            <span className="capitalize">{n.channel}</span>
                        </p>
                    </section>

                    {n.deliveries && n.deliveries.length > 0 && (
                        <ProviderLog deliveries={n.deliveries} />
                    )}
                </aside>
            </div>
        </EventLayout>
    );
}

/** The provider's raw updates, folded away. */
function ProviderLog({
    deliveries,
}: {
    deliveries: NonNullable<Notification['deliveries']>;
}) {
    const [open, setOpen] = useState(false);

    return (
        <section className="rounded-2xl bg-card shadow-card">
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen((value) => !value)}
                className="flex w-full items-center justify-between px-5 py-3.5 text-sm font-bold"
            >
                Provider log
                <span className="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                    {deliveries.length}
                    <ChevronDown
                        className={cn(
                            'size-4 transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </span>
            </button>
            {open && (
                <ul className="border-t border-border px-5 py-2">
                    {deliveries.map((delivery) => (
                        <li
                            key={delivery.id}
                            className="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2 text-sm last:border-0"
                        >
                            <span className="font-semibold capitalize">
                                {delivery.status}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {formatDateTime(delivery.created_at)}
                            </span>
                            <span className="w-full truncate font-mono text-[11px] text-subtle">
                                {delivery.provider_id ?? '—'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
