import { useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { accept } from '@/routes/client/invitation';

/** Joining a client account from an invitation email: choose a password. */
export default function AcceptInvitation({
    email,
    token,
}: {
    email: string;
    token: string;
}) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout title="Join your team">
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(accept.url(), {
                        onFinish: () =>
                            form.reset('password', 'password_confirmation'),
                    });
                }}
            >
                <p className="text-sm text-muted-foreground">
                    Choose a password to sign in. You’ll use it with this email
                    from now on.
                </p>
                <FormField
                    label="Email"
                    htmlFor="email"
                    error={form.errors.email}
                >
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </FormField>
                <FormField
                    label="Password"
                    htmlFor="password"
                    error={form.errors.password}
                >
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        autoFocus
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
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
                <Button
                    type="submit"
                    className="w-full"
                    size="lg"
                    disabled={form.processing}
                >
                    Join and sign in
                </Button>
            </form>
        </AuthLayout>
    );
}
