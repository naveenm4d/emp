import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { PageHeader } from '@/components/shared/page-header';
import { buttonVariants } from '@/components/ui/button';
import { InvitationFrame } from '@/components/web/invitation/invitation-frame';
import { RsvpActions } from '@/components/web/invitation/rsvp-actions';
import { useFonts } from '@/components/web/invitation/use-fonts';
import AdminLayout from '@/layouts/admin-layout';
import { show } from '@/routes/admin/templates';

export default function PreviewTemplate({
    template,
    version,
    design,
}: {
    template: { id: string; name: string; key: string };
    version: string;
    design: { html: string; fonts: string[]; scripts: string[] };
}) {
    useFonts(design.fonts);

    return (
        <AdminLayout>
            <Head title={`Preview ${template.name}`} />
            <PageHeader
                title={`${template.name} v${version}`}
                description="Filled with sample details. Photos show placeholders; video and music are left empty."
                actions={
                    <Link
                        href={show.url(template.id)}
                        className={buttonVariants({ variant: 'outline' })}
                    >
                        <ArrowLeft /> Back to template
                    </Link>
                }
            />
            <div className="overflow-hidden rounded-xl border border-border bg-white">
                <InvitationFrame
                    html={design.html}
                    scripts={design.scripts}
                    rsvp={
                        <RsvpActions
                            mode="preview"
                            rsvp={null}
                            actions={null}
                        />
                    }
                />
            </div>
        </AdminLayout>
    );
}
