import { Head, useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import ClientLayout from '@/layouts/client-layout';
import { password, update } from '@/routes/client/profile';
import type { Client, Resource } from '@/types';

export default function EditProfile({
    client: { data: client },
}: {
    client: Resource<Client>;
}) {
    const profile = useForm({ name: client.name, email: client.email });
    const pwd = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <ClientLayout>
            <Head title="Profile" />
            <PageHeader title="Profile" description="Your account details." />

            <div className="grid max-w-2xl gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Account</CardTitle>
                    </CardHeader>
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
                                        profile.setData('name', e.target.value)
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
                                        profile.setData('email', e.target.value)
                                    }
                                />
                            </FormField>
                            <Button type="submit" disabled={profile.processing}>
                                Save
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Password</CardTitle>
                    </CardHeader>
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
                                        pwd.setData('password', e.target.value)
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
            </div>
        </ClientLayout>
    );
}
