import { Head, Link, router, useForm } from '@inertiajs/react';
import { LayoutTemplate, Upload } from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/shared/empty-state';
import { PageHeader } from '@/components/shared/page-header';
import { Pagination } from '@/components/shared/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { templatePrice } from '@/lib/format';
import { useStaffCan } from '@/lib/permissions';
import { index, show, store } from '@/routes/admin/templates';
import type { Option, Paginated, Template } from '@/types';

type Filters = {
    search: string | null;
    category: string | null;
    status: string | null;
};

export default function TemplatesIndex({
    templates,
    filters,
    categories,
}: {
    templates: Paginated<Template>;
    filters: Filters;
    categories: Option[];
}) {
    const can = useStaffCan();
    const [uploading, setUploading] = useState(false);

    const filter = (changes: Partial<Filters>) =>
        router.get(
            index.url(),
            { ...filters, ...changes },
            { preserveState: true, replace: true },
        );

    return (
        <AdminLayout>
            <Head title="Templates" />
            <PageHeader
                title="Templates"
                description="Invitation designs clients choose from."
                actions={
                    can('templates.manage') && (
                        <Button onClick={() => setUploading(true)}>
                            <Upload /> Upload template
                        </Button>
                    )
                }
            />

            <div className="mb-4 flex flex-wrap gap-2">
                <Input
                    className="max-w-xs"
                    placeholder="Search name, key or author…"
                    defaultValue={filters.search ?? ''}
                    onKeyDown={(e) =>
                        e.key === 'Enter' &&
                        filter({ search: e.currentTarget.value || null })
                    }
                />
                <Select
                    className="w-44"
                    aria-label="Category"
                    value={filters.category ?? ''}
                    onChange={(e) =>
                        filter({ category: e.target.value || null })
                    }
                >
                    <option value="">All categories</option>
                    {categories.map((c) => (
                        <option key={c.value} value={c.value}>
                            {c.label}
                        </option>
                    ))}
                </Select>
                <Select
                    className="w-36"
                    aria-label="Status"
                    value={filters.status ?? ''}
                    onChange={(e) => filter({ status: e.target.value || null })}
                >
                    <option value="">Any status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </Select>
            </div>

            <div className="overflow-hidden rounded-lg bg-card shadow-card">
                {templates.data.length === 0 ? (
                    <EmptyState
                        icon={LayoutTemplate}
                        title="No templates found"
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-4">Template</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Price</TableHead>
                                <TableHead>Latest</TableHead>
                                <TableHead>Versions</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="pr-4 text-right">
                                    Order
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {templates.data.map((template) => (
                                <TableRow
                                    key={template.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(show.url(template))
                                    }
                                >
                                    <TableCell className="pl-4">
                                        <div className="flex items-center gap-3">
                                            <Thumbnail template={template} />
                                            <div>
                                                <Link
                                                    href={show.url(template)}
                                                    className="font-medium hover:underline"
                                                    onClick={(e) =>
                                                        e.stopPropagation()
                                                    }
                                                >
                                                    {template.name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {template.key}
                                                    {template.client &&
                                                        ` · custom for ${template.client.name}`}
                                                </p>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {template.category_label}
                                    </TableCell>
                                    <TableCell>
                                        {templatePrice(template)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {template.version
                                            ? `v${template.version}`
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {template.versions_count}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                template.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {template.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="pr-4 text-right tabular-nums">
                                        {template.sort_order}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
                <Pagination meta={templates.meta} />
            </div>

            <UploadDialog open={uploading} onOpenChange={setUploading} />
        </AdminLayout>
    );
}

function Thumbnail({ template }: { template: Template }) {
    return template.thumbnail_url ? (
        <img
            src={template.thumbnail_url}
            alt=""
            className="size-10 rounded-md object-cover"
        />
    ) : (
        <span className="flex size-10 items-center justify-center rounded-md bg-muted text-sm font-semibold text-muted-foreground">
            {template.name.charAt(0)}
        </span>
    );
}

function UploadDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<{ package: File | null }>({ package: null });

    const close = (next: boolean) => {
        if (!next) {
            form.reset();
            form.clearErrors();
        }

        onOpenChange(next);
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Upload a template</DialogTitle>
                    <DialogDescription>
                        A new key creates a template; a higher version of an
                        existing key becomes its latest version.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4 p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(store.url(), {
                            forceFormData: true,
                            onSuccess: () => close(false),
                        });
                    }}
                >
                    <pre className="rounded-lg bg-muted p-3 text-xs leading-relaxed text-muted-foreground">
                        {`my-template.zip
├── template.json   key, name, version, category, price…
├── index.html      markup with {{ placeholders }}
├── css/*.css       optional, loaded in name order
├── js/*.js         optional, run in name order
├── assets/…        optional images ({{ asset:… }})
└── thumbnail.webp  optional (png / jpg)`}
                    </pre>
                    <div className="space-y-1.5">
                        <Input
                            type="file"
                            accept=".zip,application/zip"
                            aria-invalid={!!form.errors.package}
                            onChange={(e) =>
                                form.setData(
                                    'package',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        {form.errors.package && (
                            <p className="text-xs text-destructive">
                                {form.errors.package}
                            </p>
                        )}
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={!form.data.package || form.processing}
                    >
                        {form.processing ? 'Importing…' : 'Import template'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
