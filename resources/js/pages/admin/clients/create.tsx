import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SegmentedControl } from '@/components/ui/segmented-control';
import AdminLayout from '@/layouts/admin-layout';
import { store } from '@/routes/admin/clients';

type SignIn = 'invite' | 'password';

export default function CreateClient() {
    const [signIn, setSignIn] = useState<SignIn>('invite');
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AdminLayout>
            <Head title="Add client" />
            <PageHeader
                title="Add client"
                description="Create an account on a client's behalf. It starts on the Starter plan; change the plan on the client's page."
            />

            <form
                className="max-w-xl"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.transform((data) =>
                        signIn === 'invite'
                            ? {
                                  ...data,
                                  password: '',
                                  password_confirmation: '',
                              }
                            : data,
                    );
                    form.post(store.url());
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Account</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            label="Name"
                            htmlFor="name"
                            error={form.errors.name}
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                autoFocus
                            />
                        </FormField>
                        <FormField
                            label="Email"
                            htmlFor="email"
                            error={form.errors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </FormField>

                        <div className="space-y-2">
                            <p className="text-sm font-medium">Signing in</p>
                            <SegmentedControl
                                variant="box"
                                label="How the owner signs in"
                                value={signIn}
                                onChange={setSignIn}
                                items={[
                                    {
                                        value: 'invite',
                                        label: 'Email an invitation',
                                    },
                                    {
                                        value: 'password',
                                        label: 'Set a password',
                                    },
                                ]}
                            />
                        </div>

                        {signIn === 'invite' ? (
                            <p className="text-sm text-muted-foreground motion-safe:animate-fade-in">
                                The owner gets an email with a link to choose
                                their password.
                            </p>
                        ) : (
                            <div className="space-y-4 motion-safe:animate-fade-in">
                                <FormField
                                    label="Temporary password"
                                    htmlFor="password"
                                    error={form.errors.password}
                                >
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password}
                                        onChange={(e) =>
                                            form.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Confirm password"
                                    htmlFor="password_confirmation"
                                >
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        value={form.data.password_confirmation}
                                        onChange={(e) =>
                                            form.setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Button
                    type="submit"
                    size="lg"
                    className="mt-6"
                    disabled={form.processing}
                >
                    Create client
                </Button>
            </form>
        </AdminLayout>
    );
}
