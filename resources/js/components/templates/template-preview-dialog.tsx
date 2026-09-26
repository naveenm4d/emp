import { useHttp } from '@inertiajs/react';
import { RotateCw } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { InvitationFrame } from '@/components/web/invitation/invitation-frame';
import { RsvpActions } from '@/components/web/invitation/rsvp-actions';
import { useFonts } from '@/components/web/invitation/use-fonts';
import { templatePrice } from '@/lib/format';
import type { Template } from '@/types';

type Preview = {
    id: string;
    name: string;
    version: string;
    html: string;
    fonts: string[];
    scripts: string[];
};

export type PreviewAction = {
    label: string;
    onClick: (template: Template) => void;
    disabled?: boolean;
};

type TemplatePreviewDialogProps = {
    /** The template to preview; null closes the dialog. */
    template: Template | null;
    previewUrl: (template: Template) => string;
    /** Event details typed so far; the preview shows samples for the rest. */
    details?: Record<string, string>;
    action: PreviewAction;
    onClose: () => void;
};

/**
 * Shows a template the way guests will see it (its CSS, fonts and JS),
 * filled with the event's details so far, with one call to action.
 */
export function TemplatePreviewDialog({
    template,
    previewUrl,
    details = {},
    action,
    onClose,
}: TemplatePreviewDialogProps) {
    const http = useHttp<Record<string, never>, Preview>();
    const [failed, setFailed] = useState(false);

    const query = new URLSearchParams(
        Object.entries(details).filter(([, value]) => value !== ''),
    ).toString();
    const url = template
        ? `${previewUrl(template)}${query ? `?${query}` : ''}`
        : null;

    const load = useCallback(() => {
        if (!url) {
            return;
        }

        setFailed(false);
        http.cancel();
        http.get(url).catch(() => setFailed(true));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url]);

    useEffect(load, [load]);

    const preview =
        http.response && template && http.response.id === template.id
            ? http.response
            : null;

    return (
        <Dialog
            open={template !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="h-[calc(100dvh-2rem)] max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="flex flex-wrap items-center gap-2">
                        {template?.name}
                        {template && (
                            <Badge
                                variant={
                                    template.is_free ? 'secondary' : 'outline'
                                }
                            >
                                {templatePrice(template)}
                            </Badge>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {template?.category_label} · Preview with your event
                        details; empty fields show sample text.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto bg-muted/40">
                    {failed ? (
                        <div className="flex flex-col items-center gap-3 py-24 text-center text-muted-foreground">
                            <p>The preview could not be loaded.</p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={load}
                            >
                                <RotateCw /> Try again
                            </Button>
                        </div>
                    ) : preview ? (
                        <PreviewFrame preview={preview} />
                    ) : (
                        <PreviewSkeleton />
                    )}
                </div>

                <div className="flex items-center justify-end gap-2 border-t p-4">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Close
                    </Button>
                    <Button
                        type="button"
                        disabled={!template || action.disabled}
                        onClick={() => template && action.onClick(template)}
                    >
                        {action.label}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function PreviewFrame({ preview }: { preview: Preview }) {
    useFonts(preview.fonts);

    return (
        <InvitationFrame
            html={preview.html}
            scripts={preview.scripts}
            rsvp={<RsvpActions mode="preview" rsvp={null} actions={null} />}
        />
    );
}

function PreviewSkeleton() {
    return (
        <div className="mx-auto max-w-md animate-pulse space-y-4 p-8">
            <div className="mx-auto h-3 w-24 rounded bg-muted" />
            <div className="mx-auto h-8 w-3/4 rounded bg-muted" />
            <div className="aspect-[4/3] rounded-xl bg-muted" />
            <div className="mx-auto h-4 w-1/2 rounded bg-muted" />
            <div className="mx-auto h-4 w-2/3 rounded bg-muted" />
            <div className="mx-auto h-10 w-40 rounded-lg bg-muted" />
        </div>
    );
}
