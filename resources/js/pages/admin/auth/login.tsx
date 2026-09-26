import { useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes/admin';

export default function StaffLogin() {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <AuthLayout
            title="EMP Admin"
            description="Sign in with your staff account."
            badge="Staff only"
        >
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
                        autoComplete="username"
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
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.remember}
                        onChange={(e) =>
                            form.setData('remember', e.target.checked)
                        }
                    />
                    Keep me signed in
                </label>
                <Button
                    type="submit"
                    className="w-full"
                    size="lg"
                    disabled={form.processing}
                >
                    Sign in
                </Button>
            </form>
        </AuthLayout>
    );
}
