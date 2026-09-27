import { Link, useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { login, register } from '@/routes/client';
import { request as passwordRequest } from '@/routes/client/password';

export default function Login({ status }: { status?: string }) {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <AuthLayout
            title="Sign in to EMP"
            description="Manage your events, guests and invitations."
        >
            {status && (
                <p className="mb-4 rounded-md bg-success-muted p-2 text-sm text-success">
                    {status}
                </p>
            )}
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(login.url(), {
                        onFinish: () => form.reset('password'),
                    });
                }}
            >
                <FormField
                    label="Email"
                    htmlFor="email"
                    error={form.errors.email}
                >
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        autoFocus
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
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                    />
                </FormField>
                <div className="flex items-center justify-between text-sm">
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.remember}
                            onChange={(e) =>
                                form.setData('remember', e.target.checked)
                            }
                        />
                        Remember me
                    </label>
                    <Link
                        href={passwordRequest.url()}
                        className="text-primary hover:underline"
                    >
                        Forgot password?
                    </Link>
                </div>
                <Button
                    type="submit"
                    className="w-full"
                    size="lg"
                    disabled={form.processing}
                >
                    Sign in
                </Button>
            </form>
            <p className="mt-4 text-center text-sm text-muted-foreground">
                New to EMP?{' '}
                <Link
                    href={register.url()}
                    className="text-primary hover:underline"
                >
                    Create an account
                </Link>
            </p>
        </AuthLayout>
    );
}
