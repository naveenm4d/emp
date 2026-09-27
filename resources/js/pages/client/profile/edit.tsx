import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronRight, Gem, LogOut } from 'lucide-react';

import { AppearancePicker } from '@/components/shared/appearance-picker';
import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { SectionLabel } from '@/components/shared/section-label';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import ClientLayout from '@/layouts/client-layout';
import { initials } from '@/lib/format';
import { useClientPlan } from '@/lib/plans';
import { logout, membership } from '@/routes/client';
import { password, update } from '@/routes/client/profile';
import type { Client, Resource } from '@/types';

export default function EditProfile({
    client: { data: client },
}: {
    client: Resource<Client>;
}) {
    const plan = useClientPlan();
    const profile = useForm({ name: client.name, email: client.email });
    const pwd = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <ClientLayout>
            <Head title="Account" />
            <PageHeader title="Account" className="max-w-2xl gap-4 pb-4">
                <div className="flex items-center gap-3">
                    <div className="flex size-12 items-center justify-center rounded-full bg-strong text-[15px] font-bold text-strong-foreground">
                        {initials(client.name)}
                    </div>
                    <div className="flex min-w-0 flex-col">
                        <span className="truncate text-base font-bold">
                            {client.name}
                        </span>
                        {plan && (
                            <span className="text-xs text-muted-foreground">
                                {plan.label} plan
                            </span>
                        )}
                    </div>
                </div>
            </PageHeader>

            <div className="grid max-w-2xl gap-4.5 pt-1">
                <section className="flex flex-col gap-2.5">
                    <SectionLabel>Appearance</SectionLabel>
                    <Card className="p-3.5">
                        <AppearancePicker />
                    </Card>
                    <p className="px-1 text-xs leading-snug text-muted-foreground">
                        System matches your phone’s light or dark setting. Guest
                        pages and invitations keep their own design.
                    </p>
                </section>

                <section className="flex flex-col gap-2.5">
                    <SectionLabel>General</SectionLabel>
                    <Card className="gap-0 py-0">
                        {plan && (
                            <Link
                                href={membership.url()}
                                className="flex items-center gap-3 border-b border-border p-3.5"
                            >
                                <Gem
                                    className="size-5 text-muted-foreground"
                                    strokeWidth={1.75}
                                />
                                <span className="flex-1 text-sm font-semibold">
                                    Membership
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {plan.label}
                                </span>
                                <ChevronRight className="size-4 text-subtle" />
                            </Link>
                        )}
                        <Link
                            href={logout.url()}
                            method="post"
                            as="button"
                            className="flex items-center gap-3 p-3.5 text-left text-destructive"
                        >
                            <LogOut className="size-5" strokeWidth={1.75} />
                            <span className="flex-1 text-sm font-semibold">
                                Sign out
                            </span>
                        </Link>
                    </Card>
                </section>

                <section className="flex flex-col gap-2.5">
                    <SectionLabel>Profile</SectionLabel>
                    <Card>
                        <CardContent>
                            <form
                                className="space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    profile.patch(update.url(), {
                                        preserveScroll: true,
                                    });
                                }}
                            >
                                <FormField
                                    label="Name"
                                    htmlFor="name"
                                    error={profile.errors.name}
                                >
                                    <Input
                                        id="name"
                                        value={profile.data.name}
                                        onChange={(e) =>
                                            profile.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Email"
                                    htmlFor="email"
                                    error={profile.errors.email}
                                >
                                    <Input
                                        id="email"
                                        type="email"
                                        value={profile.data.email}
                                        onChange={(e) =>
                                            profile.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <Button
                                    type="submit"
                                    disabled={profile.processing}
                                >
                                    Save
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </section>

                <section className="flex flex-col gap-2.5">
                    <SectionLabel>Password</SectionLabel>
                    <Card>
                        <CardContent>
                            <form
                                className="space-y-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    pwd.put(password.url(), {
                                        preserveScroll: true,
                                        onSuccess: () => pwd.reset(),
                                    });
                                }}
                            >
                                <FormField
                                    label="Current password"
                                    htmlFor="current_password"
                                    error={pwd.errors.current_password}
                                >
                                    <Input
                                        id="current_password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={pwd.data.current_password}
                                        onChange={(e) =>
                                            pwd.setData(
                                                'current_password',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="New password"
                                    htmlFor="new_password"
                                    error={pwd.errors.password}
                                >
                                    <Input
                                        id="new_password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={pwd.data.password}
                                        onChange={(e) =>
                                            pwd.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Confirm new password"
                                    htmlFor="password_confirmation"
                                >
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        value={pwd.data.password_confirmation}
                                        onChange={(e) =>
                                            pwd.setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <Button type="submit" disabled={pwd.processing}>
                                    Update password
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </ClientLayout>
    );
}
