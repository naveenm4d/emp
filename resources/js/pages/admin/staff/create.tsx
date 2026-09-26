import { Head, useForm } from '@inertiajs/react';

import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import AdminLayout from '@/layouts/admin-layout';
import { store } from '@/routes/admin/staff';
import type { Option } from '@/types';

type Props = {
    roles: Option[];
    permissions: Option[];
    roleDefaults: Record<string, string[]>;
};

export default function CreateStaffMember({
    roles,
    permissions,
    roleDefaults,
}: Props) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'viewer',
        permissions: roleDefaults.viewer ?? [],
    });

    const toggle = (permission: string) =>
        form.setData(
            'permissions',
            form.data.permissions.includes(permission)
                ? form.data.permissions.filter((p) => p !== permission)
                : [...form.data.permissions, permission],
        );

    return (
        <AdminLayout>
            <Head title="Add staff member" />
            <PageHeader
                title="Add staff member"
                description="Give a team member access to the admin console."
            />

            <form
                className="grid max-w-3xl gap-6 lg:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
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
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Access</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <FormField
                            label="Role"
                            htmlFor="role"
                            error={form.errors.role}
                        >
                            <Select
                                id="role"
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData({
                                        ...form.data,
                                        role: e.target.value,
                                        permissions:
                                            roleDefaults[e.target.value] ?? [],
                                    })
                                }
                            >
                                {roles.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </Select>
                        </FormField>
                        {form.data.role === 'super_admin' ? (
                            <p className="text-sm text-muted-foreground">
                                Super admins have every permission.
                            </p>
                        ) : (
                            <fieldset className="space-y-2">
                                <legend className="mb-2 text-sm font-medium">
                                    Permissions
                                </legend>
                                {permissions.map((p) => (
                                    <label
                                        key={p.value}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.data.permissions.includes(
                                                p.value,
                                            )}
                                            onChange={() => toggle(p.value)}
                                        />
                                        <span className="font-mono text-xs">
                                            {p.value}
                                        </span>
                                    </label>
                                ))}
                                {form.errors.permissions && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.permissions}
                                    </p>
                                )}
                            </fieldset>
                        )}
                    </CardContent>
                </Card>

                <div className="lg:col-span-2">
                    <Button type="submit" size="lg" disabled={form.processing}>
                        Create staff member
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
