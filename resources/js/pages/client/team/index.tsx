import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Crown,
    KeyRound,
    Pencil,
    RotateCw,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';

import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { FormField } from '@/components/shared/form-field';
import { Section, Switch, ToggleRow } from '@/components/shared/form-section';
import { PageHeader } from '@/components/shared/page-header';
import { SectionLabel } from '@/components/shared/section-label';
import { StatusBadge } from '@/components/shared/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { POPUP } from '@/components/web/invitation/registration-dialog';
import ClientLayout from '@/layouts/client-layout';
import { formatShortDate, initials, timeAgo } from '@/lib/format';
import { useClientPlan } from '@/lib/plans';
import { membership } from '@/routes/client';
import { destroy, owner, resend, store, update } from '@/routes/client/team';
import type { ClientPermission, ClientUser } from '@/types';

type TeamEvent = { id: string; title: string; event_date: string | null };

type PermissionOption = {
    value: ClientPermission;
    label: string;
    description: string;
};

type Props = {
    members: { data: ClientUser[] };
    events: TeamEvent[];
    permissions: PermissionOption[];
};

/**
 * The people who sign in to the account (2 on Celebration, 5 on Business):
 * invite them, choose what they can do and which events they see.
 */
export default function TeamIndex({
    members: { data: members },
    events,
    permissions,
}: Props) {
    const me = usePage().props.auth.user;
    const plan = useClientPlan();
    const confirmation = useConfirm();
    const [inviting, setInviting] = useState(false);
    const [editing, setEditing] = useState<ClientUser | null>(null);

    const used = plan?.users_used ?? members.length;
    const allowed = plan?.users_allowed ?? null;
    const full = allowed !== null && used >= allowed;

    return (
        <ClientLayout>
            <Head title="Team" />
            <PageHeader
                title="Team"
                description={
                    allowed === null
                        ? `${used} ${used === 1 ? 'person signs' : 'people sign'} in to this account`
                        : `${used} of ${allowed} ${allowed === 1 ? 'person' : 'people'} on your ${plan?.label} plan`
                }
                actions={
                    <Button
                        size="lg"
                        disabled={full}
                        title={
                            full
                                ? 'Your plan has no room for another person.'
                                : undefined
                        }
                        onClick={() => setInviting(true)}
                    >
                        <UserPlus /> Invite
                    </Button>
                }
                className="max-w-3xl"
            />

            <div className="grid max-w-3xl gap-4.5 pt-1">
                {full && (
                    <p className="rounded-2xl bg-warning-muted px-4 py-3 text-sm motion-safe:animate-fade-in">
                        Your {plan?.label} plan includes {allowed}{' '}
                        {allowed === 1 ? 'person' : 'people'}. Remove someone,
                        or{' '}
                        <Link
                            href={membership.url()}
                            className="font-semibold text-link"
                        >
                            see the plans
                        </Link>{' '}
                        to add more.
                    </p>
                )}

                <section className="flex flex-col gap-2.5">
                    <SectionLabel>People</SectionLabel>
                    <div className="relative overflow-hidden rounded-2xl bg-card shadow-card">
                        <ConfirmBar
                            request={confirmation.request}
                            onCancel={confirmation.cancel}
                        />
                        {members.map((member) => (
                            <MemberRow
                                key={member.id}
                                member={member}
                                events={events}
                                isMe={member.id === me?.id}
                                canTransfer={!!me?.is_owner}
                                onEdit={() => setEditing(member)}
                                onRemove={() =>
                                    confirmation.ask({
                                        title: `Remove ${member.name}? They can no longer sign in to this account.`,
                                        confirmLabel: 'Remove',
                                        onConfirm: () =>
                                            router.delete(destroy.url(member), {
                                                preserveScroll: true,
                                            }),
                                    })
                                }
                                onTransfer={() =>
                                    confirmation.ask({
                                        title: `Make ${member.name} the owner? You stay on the team with full access.`,
                                        confirmLabel: 'Make owner',
                                        onConfirm: () =>
                                            router.post(
                                                owner.url(member),
                                                {},
                                                { preserveScroll: true },
                                            ),
                                    })
                                }
                            />
                        ))}
                    </div>
                    <p className="px-1 text-xs leading-snug text-muted-foreground">
                        The owner can do everything. Everyone else sees only the
                        events you give them, and does only what you allow.
                    </p>
                </section>
            </div>

            {inviting && (
                <MemberDialog
                    events={events}
                    permissions={permissions}
                    onClose={() => setInviting(false)}
                />
            )}
            {editing && (
                <MemberDialog
                    key={editing.id}
                    member={editing}
                    events={events}
                    permissions={permissions}
                    onClose={() => setEditing(null)}
                />
            )}
        </ClientLayout>
    );
}

function MemberRow({
    member,
    events,
    isMe,
    canTransfer,
    onEdit,
    onRemove,
    onTransfer,
}: {
    member: ClientUser;
    events: TeamEvent[];
    isMe: boolean;
    canTransfer: boolean;
    onEdit: () => void;
    onRemove: () => void;
    onTransfer: () => void;
}) {
    const manageable = !member.is_owner && !isMe;
    const eventCount = member.event_ids?.length ?? 0;
    const access = member.all_events
        ? 'All events'
        : eventCount === 0
          ? 'No events yet'
          : eventCount === 1
            ? (events.find((event) => event.id === member.event_ids?.[0])
                  ?.title ?? '1 event')
            : `${eventCount} events`;

    return (
        <div className="flex flex-wrap items-center gap-3 border-b border-border p-3.5 last:border-b-0 sm:flex-nowrap">
            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-strong text-[13px] font-bold text-strong-foreground">
                {initials(member.name)}
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="truncate text-sm font-semibold">
                        {member.name}
                        {isMe && (
                            <span className="font-normal text-muted-foreground">
                                {' '}
                                (you)
                            </span>
                        )}
                    </span>
                    {member.is_owner ? (
                        <StatusBadge status="admin" label="Owner" />
                    ) : member.pending ? (
                        <StatusBadge status="pending" label="Invited" />
                    ) : null}
                </div>
                <div className="truncate text-xs text-muted-foreground">
                    {member.email}
                </div>
                {!member.is_owner && (
                    <div className="mt-0.5 truncate text-xs text-subtle">
                        {access} · {member.permissions.length}{' '}
                        {member.permissions.length === 1
                            ? 'permission'
                            : 'permissions'}
                        {member.pending && member.invited_at
                            ? ` · invited ${timeAgo(member.invited_at)}`
                            : ''}
                    </div>
                )}
            </div>
            {manageable && (
                <div className="flex shrink-0 items-center gap-1">
                    {member.pending && (
                        <IconButton
                            label="Send the invitation again"
                            onClick={() =>
                                router.post(
                                    resend.url(member),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RotateCw className="size-4" />
                        </IconButton>
                    )}
                    {canTransfer && !member.pending && (
                        <IconButton label="Make owner" onClick={onTransfer}>
                            <Crown className="size-4" />
                        </IconButton>
                    )}
                    <IconButton label="Edit access" onClick={onEdit}>
                        <Pencil className="size-4" />
                    </IconButton>
                    <IconButton label="Remove" onClick={onRemove} danger>
                        <Trash2 className="size-4" />
                    </IconButton>
                </div>
            )}
        </div>
    );
}

function IconButton({
    label,
    onClick,
    danger,
    children,
}: {
    label: string;
    onClick: () => void;
    danger?: boolean;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            onClick={onClick}
            className={
                danger
                    ? 'inline-flex size-8 items-center justify-center rounded-md text-destructive transition-colors outline-none hover:bg-destructive-muted focus-visible:ring-3 focus-visible:ring-ring/50'
                    : 'inline-flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50'
            }
        >
            {children}
        </button>
    );
}

/** Inviting someone, or changing a member's access. */
function MemberDialog({
    member,
    events,
    permissions,
    onClose,
}: {
    member?: ClientUser;
    events: TeamEvent[];
    permissions: PermissionOption[];
    onClose: () => void;
}) {
    const form = useForm({
        name: member?.name ?? '',
        email: member?.email ?? '',
        permissions: member?.permissions ?? ([] as ClientPermission[]),
        all_events: member?.all_events ?? false,
        event_ids: member?.event_ids ?? ([] as string[]),
    });

    const toggle = <T,>(list: T[], value: T, on: boolean): T[] =>
        on ? [...list, value] : list.filter((item) => item !== value);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: (page: { props: { flash: { error: string | null } } }) =>
                !page.props.flash.error && onClose(),
        };

        if (member) {
            form.transform(({ permissions, all_events, event_ids }) => ({
                permissions,
                all_events,
                event_ids,
            }));
            form.patch(update.url(member), options);
        } else {
            form.post(store.url(), options);
        }
    };

    const eventError = Object.entries(form.errors).find(([key]) =>
        key.startsWith('event_ids'),
    )?.[1];

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className={POPUP}>
                <form
                    onSubmit={submit}
                    className="flex min-h-0 flex-1 flex-col"
                    noValidate
                >
                    <div className="px-4 pt-5 pr-12">
                        <DialogDescription className="text-xs">
                            {member
                                ? `${member.name} · ${member.email}`
                                : 'They get an email with a link to set a password.'}
                        </DialogDescription>
                        <DialogTitle className="text-base font-bold">
                            {member ? 'Edit access' : 'Invite someone'}
                        </DialogTitle>
                    </div>

                    <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4 py-4">
                        {!member && (
                            <div className="grid gap-3">
                                <FormField
                                    label="Name"
                                    htmlFor="member-name"
                                    error={form.errors.name}
                                >
                                    <Input
                                        id="member-name"
                                        autoFocus
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Email"
                                    htmlFor="member-email"
                                    error={form.errors.email}
                                >
                                    <Input
                                        id="member-email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        )}

                        <Section
                            icon={KeyRound}
                            title="What they can do"
                            description="They can always see the events they’re given."
                        >
                            <div className="divide-y divide-border">
                                {permissions.map((permission) => (
                                    <ToggleRow
                                        key={permission.value}
                                        label={permission.label}
                                        hint={permission.description}
                                        checked={form.data.permissions.includes(
                                            permission.value,
                                        )}
                                        onChange={(on) =>
                                            form.setData(
                                                'permissions',
                                                toggle(
                                                    form.data.permissions,
                                                    permission.value,
                                                    on,
                                                ),
                                            )
                                        }
                                    />
                                ))}
                            </div>
                        </Section>

                        <Section
                            icon={CalendarDays}
                            title="Events they see"
                            description="Choose events, or give them all of them, including new ones."
                        >
                            <ToggleRow
                                first
                                label="All events"
                                checked={form.data.all_events}
                                onChange={(on) =>
                                    form.setData('all_events', on)
                                }
                            />
                            {!form.data.all_events && (
                                <div className="mt-2 flex flex-col divide-y divide-border border-t border-border motion-safe:animate-fade-in">
                                    {events.length === 0 && (
                                        <p className="py-3 text-xs text-muted-foreground">
                                            No events yet. Give them all events,
                                            or let them create their own.
                                        </p>
                                    )}
                                    {events.map((event) => (
                                        <label
                                            key={event.id}
                                            className="flex cursor-pointer items-center justify-between gap-4 py-2.5"
                                        >
                                            <span className="min-w-0">
                                                <span className="block truncate text-sm font-semibold">
                                                    {event.title}
                                                </span>
                                                {event.event_date && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {formatShortDate(
                                                            event.event_date,
                                                        )}
                                                    </span>
                                                )}
                                            </span>
                                            <Switch
                                                checked={form.data.event_ids.includes(
                                                    event.id,
                                                )}
                                                onChange={(on) =>
                                                    form.setData(
                                                        'event_ids',
                                                        toggle(
                                                            form.data.event_ids,
                                                            event.id,
                                                            on,
                                                        ),
                                                    )
                                                }
                                            />
                                        </label>
                                    ))}
                                </div>
                            )}
                            {eventError && (
                                <p className="mt-2 text-xs text-destructive">
                                    {eventError}
                                </p>
                            )}
                        </Section>
                    </div>

                    <div className="flex flex-col gap-2 border-t border-border px-4 pt-3 pb-6 sm:pb-4">
                        <Button
                            type="submit"
                            variant="strong"
                            className="h-13 rounded-full text-base"
                            disabled={
                                form.processing ||
                                (!member &&
                                    (!form.data.name.trim() ||
                                        !form.data.email.trim()))
                            }
                        >
                            {form.processing
                                ? 'Saving…'
                                : member
                                  ? 'Save changes'
                                  : 'Send invitation'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
