import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

import { PurchaseDialog } from '@/components/billing/purchase-dialog';
import { PageHeader } from '@/components/shared/page-header';
import { TemplateGallery } from '@/components/templates/template-picker';
import { TemplatePreviewDialog } from '@/components/templates/template-preview-dialog';
import ClientLayout from '@/layouts/client-layout';
import { templatePrice } from '@/lib/format';
import { useClientCan } from '@/lib/permissions';
import { template as buyTemplate } from '@/routes/client/billing';
import { create } from '@/routes/client/events';
import { preview } from '@/routes/client/templates';
import type { Template } from '@/types';

export default function TemplatesIndex({
    templates,
    ownedTemplateIds,
}: {
    templates: { data: Template[] };
    /** Paid designs the account can use already (bought, or used by an event). */
    ownedTemplateIds: string[];
}) {
    const [previewing, setPreviewing] = useState<Template | null>(null);
    const [buying, setBuying] = useState<Template | null>(null);
    const can = useClientCan();

    const needsPurchase = (template: Template) =>
        !template.is_free &&
        template.type !== 'custom' &&
        !ownedTemplateIds.includes(template.id);

    return (
        <ClientLayout>
            <Head title="Invitation designs" />
            <PageHeader
                title="Invitation designs"
                description="Preview any design, then start an event with the one you like. Paid designs are bought once and work for all your events."
            />
            <div className="-mx-3 -mt-3 bg-card px-4 pt-3 pb-6 md:mx-0 md:mt-0 md:rounded-xl md:p-4 md:shadow-card">
                <TemplateGallery
                    templates={templates.data}
                    onOpen={setPreviewing}
                />
            </div>
            <TemplatePreviewDialog
                template={previewing}
                previewUrl={(template) => preview.url(template)}
                onClose={() => setPreviewing(null)}
                action={
                    previewing && needsPurchase(previewing)
                        ? {
                              label: `Buy for ${templatePrice(previewing)}`,
                              disabled: !can('billing.manage'),
                              onClick: (template) => {
                                  setPreviewing(null);
                                  setBuying(template);
                              },
                          }
                        : {
                              label: 'Create an event with this design',
                              onClick: (template) =>
                                  router.visit(
                                      create.url({
                                          query: { template: template.id },
                                      }),
                                  ),
                          }
                }
            />
            {buying && (
                <PurchaseDialog
                    open
                    onClose={() => setBuying(null)}
                    title={buying.name}
                    description="Invitation design · yours for every event"
                    url={buyTemplate.url(buying)}
                    amount={buying.price}
                    currency={buying.currency}
                />
            )}
        </ClientLayout>
    );
}
