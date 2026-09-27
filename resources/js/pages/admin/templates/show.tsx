import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Eye, Trash2 } from 'lucide-react';

import { ConfirmBar, useConfirm } from '@/components/shared/confirm-bar';
import { FormField } from '@/components/shared/form-field';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateTime, templatePrice } from '@/lib/format';
import { useStaffCan } from '@/lib/permissions';
import { destroy, index, update } from '@/routes/admin/templates';
import { preview } from '@/routes/admin/templates/versions';
import type { Option, Resource, Template, TemplateVersion } from '@/types';

export default function ShowTemplate({
    template: { data: template },
    versions,
    categories,
}: {
    template: Resource<Template>;
    versions: { data: TemplateVersion[] };
    categories: Option[];
}) {
    const can = useStaffCan();
    const latest = versions.data.find((version) => version.is_latest);

    const confirmation = useConfirm();

    const remove = () =>
        confirmation.ask({
            title: `Delete "${template.name}" and all its versions? This can’t be undone.`,
            confirmLabel: 'Delete',
            onConfirm: () => router.delete(destroy.url(template)),
        });

    return (
        <AdminLayout>
            <Head title={template.name} />
            <PageHeader
                title={template.name}
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                template.is_active ? 'secondary' : 'outline'
                            }
                        >
                            {template.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                        <span>
                            {template.key} · {template.category_label} ·{' '}
                            {templatePrice(template)}
                            {template.client &&
                                ` · custom for ${template.client.name}`}
                        </span>
                    </span>
                }
                actions={
                    confirmation.request ? (
                        <ConfirmBar
                            variant="inline"
                            request={confirmation.request}
                            onCancel={confirmation.cancel}
                        />
                    ) : (
                        <>
                            <Link
                                href={index.url()}
                                className={buttonVariants({ variant: 'ghost' })}
                            >
                                <ArrowLeft /> Templates
                            </Link>
                            {latest && (
                                <Link
                                    href={preview.url({
                                        template: template.id,
                                        version: latest.id,
                                    })}
                                    className={buttonVariants({
                                        variant: 'outline',
                                    })}
                                >
                                    <Eye /> Preview
                                </Link>
                            )}
                            {can('templates.manage') && (
                                <Button
                                    variant="ghost"
                                    onClick={remove}
                                    aria-label="Delete template"
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )
                }
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Versions</CardTitle>
                        </CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-4">
                                            Version
                                        </TableHead>
                                        <TableHead>Published</TableHead>
                                        <TableHead>Media slots</TableHead>
                                        <TableHead>Events using it</TableHead>
                                        <TableHead className="pr-4 text-right" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {versions.data.map((version) => (
                                        <TableRow key={version.id}>
                                            <TableCell className="pl-4">
                                                <span className="font-medium">
                                                    v{version.version}
                                                </span>{' '}
                                                {version.is_latest && (
                                                    <Badge variant="secondary">
                                                        Latest
                                                    </Badge>
                                                )}{' '}
                                                {version.has_scripts && (
                                                    <Badge variant="outline">
                                                        JS
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDateTime(
                                                    version.published_at,
                                                )}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {version.slots}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {version.events_count}
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                <Link
                                                    href={preview.url({
                                                        template: template.id,
                                                        version: version.id,
                                                    })}
                                                    className="text-sm hover:underline"
                                                >
                                                    Preview
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                    <p className="text-xs text-muted-foreground">
                        Versions are immutable. Upload the same key with a
                        higher version to publish a change; events keep the
                        version they were designed with until the client
                        upgrades.
                    </p>
                </div>

                {can('templates.manage') ? (
                    <EditTemplateCard
                        template={template}
                        categories={categories}
                    />
                ) : (
                    <TemplateDetailsCard template={template} />
                )}
            </div>
        </AdminLayout>
    );
}

type TemplateForm = {
    name: string;
    description: string;
    category: string;
    tags: string;
    price: string;
    currency: string;
    display_price: string;
    sort_order: number;
    is_active: boolean;
};

function EditTemplateCard({
    template,
    categories,
}: {
    template: Template;
    categories: Option[];
}) {
    const form = useForm<TemplateForm>({
        name: template.name,
        description: template.description ?? '',
        category: template.category,
        tags: template.tags.join(', '),
        price: (template.price / 100).toFixed(2),
        currency: template.currency,
        display_price: template.display_price ?? '',
        sort_order: template.sort_order,
        is_active: template.is_active,
    });

    const text = (key: 'name' | 'currency' | 'display_price') => ({
        id: key,
        value: form.data[key],
        'aria-invalid': !!form.errors[key],
        onChange: (e: React.ChangeEvent<HTMLInputElement>) =>
            form.setData(key, e.target.value),
    });

    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Catalogue details</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            price: Math.round(Number(data.price || 0) * 100),
                            tags: data.tags
                                .split(',')
                                .map((tag) => tag.trim())
                                .filter(Boolean),
                            display_price: data.display_price || null,
                        }));
                        form.patch(update.url(template), {
                            preserveScroll: true,
                        });
                    }}
                >
                    <FormField
                        label="Name"
                        htmlFor="name"
                        error={form.errors.name}
                    >
                        <Input {...text('name')} />
                    </FormField>
                    <FormField
                        label="Description"
                        htmlFor="description"
                        error={form.errors.description}
                    >
                        <Textarea
                            id="description"
                            rows={3}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                    </FormField>
                    <FormField
                        label="Category"
                        htmlFor="category"
                        error={form.errors.category}
                    >
                        <Select
                            id="category"
                            value={form.data.category}
                            onChange={(e) =>
                                form.setData('category', e.target.value)
                            }
                        >
                            {categories.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {c.label}
                                </option>
                            ))}
                        </Select>
                    </FormField>
                    <FormField
                        label="Tags"
                        htmlFor="tags"
                        error={form.errors.tags}
                        hint="Comma separated."
                    >
                        <Input
                            id="tags"
                            value={form.data.tags}
                            onChange={(e) =>
                                form.setData('tags', e.target.value)
                            }
                        />
                    </FormField>
                    <div className="grid grid-cols-3 gap-3">
                        <FormField
                            label="Price"
                            htmlFor="price"
                            error={form.errors.price}
                            className="col-span-2"
                        >
                            <Input
                                id="price"
                                type="number"
                                min={0}
                                step="0.01"
                                value={form.data.price}
                                aria-invalid={!!form.errors.price}
                                onChange={(e) =>
                                    form.setData('price', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            label="Currency"
                            htmlFor="currency"
                            error={form.errors.currency}
                        >
                            <Input {...text('currency')} maxLength={3} />
                        </FormField>
                    </div>
                    <FormField
                        label="Display price"
                        htmlFor="display_price"
                        error={form.errors.display_price}
                        hint='Shown to clients, e.g. "Rs. 1,490" or "Free".'
                    >
                        <Input {...text('display_price')} />
                    </FormField>
                    <FormField
                        label="Sort order"
                        htmlFor="sort_order"
                        error={form.errors.sort_order}
                        hint="Lower numbers are shown first."
                    >
                        <Input
                            id="sort_order"
                            type="number"
                            min={0}
                            value={form.data.sort_order}
                            onChange={(e) =>
                                form.setData(
                                    'sort_order',
                                    Number(e.target.value),
                                )
                            }
                        />
                    </FormField>
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData('is_active', e.target.checked)
                            }
                        />
                        <span>
                            Active
                            <span className="block text-xs text-muted-foreground">
                                Inactive templates are hidden from clients;
                                events already using them keep working.
                            </span>
                        </span>
                    </label>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Save details
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

function TemplateDetailsCard({ template }: { template: Template }) {
    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Catalogue details</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="space-y-3 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Author</dt>
                        <dd>{template.author}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Description</dt>
                        <dd>{template.description ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Tags</dt>
                        <dd>{template.tags.join(', ') || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Sort order</dt>
                        <dd>{template.sort_order}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}
