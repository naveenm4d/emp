import { Link, useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { login, register } from '@/routes/client';

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AuthLayout
            title="Create your EMP account"
            description="Start planning events in minutes."
        >
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(register.url(), {
                        onFinish: () =>
                            form.reset('password', 'password_confirmation'),
                    });
                }}
            >
                <FormField label="Name" htmlFor="name" error={form.errors.name}>
                    <Input
                        id="name"
                        autoComplete="name"
                        autoFocus
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
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
                        autoComplete="email"
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
                    Create account
                </Button>
            </form>
            <p className="mt-4 text-center text-sm text-muted-foreground">
                Already have an account?{' '}
                <Link
                    href={login.url()}
                    className="text-primary hover:underline"
                >
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
