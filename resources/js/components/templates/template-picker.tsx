import { Check, Image, Music, Video } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Template } from '@/types';

type TemplatePickerProps = {
    templates: Template[];
    value: string;
    onChange: (id: string) => void;
    invalid?: boolean;
};

/** Grid of template cards; exactly one is selected (radio semantics). */
export function TemplatePicker({
    templates,
    value,
    onChange,
    invalid,
}: TemplatePickerProps) {
    if (templates.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No templates are available yet.
            </p>
        );
    }

    return (
        <div
            role="radiogroup"
            aria-label="Invitation template"
            aria-invalid={invalid}
            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
        >
            {templates.map((template) => {
                const selected = template.id === value;

                return (
                    <button
                        key={template.id}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => onChange(template.id)}
                        className={cn(
                            'group relative flex flex-col overflow-hidden rounded-lg border text-left transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                            selected
                                ? 'border-primary ring-1 ring-primary'
                                : 'border-border hover:border-foreground/30',
                        )}
                    >
                        <div className="relative aspect-[4/3] bg-muted">
                            {template.thumbnail_url ? (
                                <img
                                    src={template.thumbnail_url}
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="flex h-full items-center justify-center bg-gradient-to-br from-muted to-secondary text-3xl font-semibold text-muted-foreground/60">
                                    {template.name.charAt(0)}
                                </div>
                            )}
                            {selected && (
                                <span className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                    <Check className="size-4" />
                                </span>
                            )}
                        </div>
                        <div className="flex flex-1 flex-col gap-1.5 p-3">
                            <div className="flex items-start justify-between gap-2">
                                <span className="font-medium">
                                    {template.name}
                                </span>
                                <Badge
                                    variant={
                                        template.is_free
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {template.display_price ??
                                        `${template.currency} ${(template.price / 100).toFixed(2)}`}
                                </Badge>
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {template.category_label} · by {template.author}
                                {template.version && ` · v${template.version}`}
                            </span>
                            <MediaSummary template={template} />
                        </div>
                    </button>
                );
            })}
        </div>
    );
}

function MediaSummary({ template }: { template: Template }) {
    const { images, videos, music } = template.media_summary;

    if (!images && !videos && !music) {
        return (
            <span className="text-xs text-muted-foreground">
                No media uploads
            </span>
        );
    }

    return (
        <span className="flex flex-wrap gap-3 text-xs text-muted-foreground">
            {images > 0 && (
                <span className="inline-flex items-center gap-1">
                    <Image className="size-3.5" /> {images}{' '}
                    {images === 1 ? 'photo' : 'photos'}
                </span>
            )}
            {videos > 0 && (
                <span className="inline-flex items-center gap-1">
                    <Video className="size-3.5" /> {videos}{' '}
                    {videos === 1 ? 'video' : 'videos'}
                </span>
            )}
            {music && (
                <span className="inline-flex items-center gap-1">
                    <Music className="size-3.5" /> music
                </span>
            )}
        </span>
    );
}
