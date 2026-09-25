import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { StatusBadge } from '@/components/shared/status-badge';
import { buttonVariants } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import InternalLayout from '@/layouts/internal-layout';
import { formatDateTime } from '@/lib/format';
import { create } from '@/routes/internal/staff';
import type { Paginated, StaffMember } from '@/types';

export default function StaffIndex({
    staff,
}: {
    staff: Paginated<StaffMember>;
}) {
    const canCreate =
        usePage().props.auth.staff?.permissions.includes('staff.create');

    return (
        <InternalLayout>
            <Head title="Staff" />
            <PageHeader
                title="Staff"
                description="EMP internal team members and their access."
                actions={
                    canCreate && (
                        <Link href={create.url()} className={buttonVariants()}>
                            <Plus /> Add staff member
                        </Link>
                    )
                }
            />

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="pl-4">Member</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Permissions</TableHead>
                            <TableHead className="pr-4">Last login</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {staff.data.map((member) => (
                            <TableRow key={member.id}>
                                <TableCell className="pl-4">
                                    <p className="font-medium">{member.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {member.email}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <StatusBadge
                                        status={member.role}
                                        label={member.role_label}
                                    />
                                </TableCell>
                                <TableCell>
                                    <StatusBadge
                                        status={
                                            member.is_active
                                                ? 'active'
                                                : 'inactive'
                                        }
                                    />
                                </TableCell>
                                <TableCell className="max-w-sm text-xs whitespace-normal text-muted-foreground">
                                    {member.role === 'super_admin'
                                        ? 'All permissions'
                                        : member.permissions.join(', ') ||
                                          'None'}
                                </TableCell>
                                <TableCell className="pr-4 text-muted-foreground">
                                    {formatDateTime(member.last_login_at)}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                <Pagination meta={staff.meta} />
            </div>
        </InternalLayout>
    );
}
