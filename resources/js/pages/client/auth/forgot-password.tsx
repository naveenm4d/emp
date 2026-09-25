import { Link, useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes/client';
import { email } from '@/routes/client/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const form = useForm({ email: '' });

    return (
        <AuthLayout
            title="Reset your password"
            description="We will email you a link to choose a new password."
        >
            {status && (
                <p className="mb-4 rounded-md bg-emerald-50 p-2 text-sm text-emerald-700">
                    {status}
                </p>
            )}
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(email.url());
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
                        autoFocus
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </FormField>
                <Button
                    type="submit"
                    className="w-full"
                    size="lg"
                    disabled={form.processing}
                >
                    Email reset link
                </Button>
            </form>
            <p className="mt-4 text-center text-sm">
                <Link
                    href={login.url()}
                    className="text-primary hover:underline"
                >
                    Back to sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
