import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { PageHeader } from '@/components/shared/page-header';
import { TemplateGallery } from '@/components/templates/template-picker';
import { TemplatePreviewDialog } from '@/components/templates/template-preview-dialog';
import ClientLayout from '@/layouts/client-layout';
import { create } from '@/routes/client/events';
import { preview } from '@/routes/client/templates';
import type { Template } from '@/types';

export default function TemplatesIndex({
    templates,
}: {
    templates: { data: Template[] };
}) {
    const [previewing, setPreviewing] = useState<Template | null>(null);

    return (
        <ClientLayout>
            <Head title="Invitation designs" />
            <PageHeader
                title="Invitation designs"
                description="Preview any design, then start an event with the one you like."
            />
            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <TemplateGallery
                    templates={templates.data}
                    onOpen={setPreviewing}
                />
            </div>
            <TemplatePreviewDialog
                template={previewing}
                previewUrl={(template) => preview.url(template)}
                onClose={() => setPreviewing(null)}
                action={{
                    label: 'Create an event with this design',
                    onClick: (template) =>
                        router.visit(
                            create.url({ query: { template: template.id } }),
                        ),
                }}
            />
        </ClientLayout>
    );
}
