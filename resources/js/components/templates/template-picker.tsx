import {
    Check,
    Eye,
    Image,
    LayoutGrid,
    Music,
    SearchX,
    Video,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { TemplatePreviewDialog } from '@/components/templates/template-preview-dialog';
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
import { templatePrice } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Template } from '@/types';

/** How many templates the form shows inline; the rest are in the browser modal. */
const INLINE_COUNT = 3;

type TemplatePickerProps = {
    templates: Template[];
    value: string;
    onChange: (id: string) => void;
    invalid?: boolean;
    /** JSON preview endpoint for a template (client or admin area). */
    previewUrl: (template: Template) => string;
    /** Event details typed so far, shown in the preview. */
    previewDetails?: Record<string, string>;
};

/**
 * The event form's design picker: a short row of templates (the chosen one
 * first) plus a modal to browse them all. Clicking a template opens its
 * preview; "Use this design" in the preview chooses it.
 */
export function TemplatePicker({
    templates,
    value,
    onChange,
    invalid,
    previewUrl,
    previewDetails,
}: TemplatePickerProps) {
    const [browsing, setBrowsing] = useState(false);
    const [previewing, setPreviewing] = useState<Template | null>(null);

    if (templates.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No templates are available yet.
            </p>
        );
    }

    const selected = templates.find((template) => template.id === value);
    const suggestions = templates.slice(0, INLINE_COUNT);

    return (
        <div className="space-y-3">
            {selected ? (
                <ChosenDesign
                    template={selected}
                    onPreview={() => setPreviewing(selected)}
                    onChange={() => setBrowsing(true)}
                />
            ) : (
                <>
                    <p className="text-xs text-muted-foreground">
                        Click a design to preview it with your details.
                    </p>
                    <TemplateGrid
                        templates={suggestions}
                        onOpen={setPreviewing}
                        invalid={invalid}
                    />
                    {templates.length > INLINE_COUNT && (
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={() => setBrowsing(true)}
                        >
                            <LayoutGrid /> Explore more templates (
                            {templates.length})
                        </Button>
                    )}
                </>
            )}

            <Dialog open={browsing} onOpenChange={setBrowsing}>
                <DialogContent className="h-[calc(100dvh-2rem)] max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Choose an invitation design</DialogTitle>
                        <DialogDescription>
                            Click a design to preview it.
                        </DialogDescription>
                    </DialogHeader>
                    <TemplateGallery
                        templates={templates}
                        selectedId={value}
                        onOpen={setPreviewing}
                        className="flex-1 overflow-y-auto"
                    />
                </DialogContent>
            </Dialog>

            <TemplatePreviewDialog
                template={previewing}
                previewUrl={previewUrl}
                details={previewDetails}
                onClose={() => setPreviewing(null)}
                action={{
                    label:
                        previewing?.id === value
                            ? 'Current design'
                            : 'Use this design',
                    disabled: previewing?.id === value,
                    onClick: (template) => {
                        onChange(template.id);
                        setPreviewing(null);
                        setBrowsing(false);
                    },
                }}
            />
        </div>
    );
}

/** The design this event uses, with ways to look at it again or pick another. */
function ChosenDesign({
    template,
    onPreview,
    onChange,
}: {
    template: Template;
    onPreview: () => void;
    onChange: () => void;
}) {
    return (
        <div className="flex flex-col gap-4 rounded-lg border border-primary/40 bg-primary/5 p-3 sm:flex-row sm:items-center">
            <button
                type="button"
                onClick={onPreview}
                aria-label={`Preview ${template.name}`}
                className="relative aspect-[4/3] w-full shrink-0 overflow-hidden rounded-md bg-muted sm:w-40"
            >
                {template.thumbnail_url ? (
                    <img
                        src={template.thumbnail_url}
                        alt=""
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <span className="flex h-full items-center justify-center bg-gradient-to-br from-muted to-secondary text-3xl font-semibold text-muted-foreground/60">
                        {template.name.charAt(0)}
                    </span>
                )}
            </button>
            <div className="min-w-0 flex-1 space-y-1">
                <p className="flex items-center gap-2 font-medium">
                    <Check className="size-4 text-primary" /> {template.name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {template.category_label} · {templatePrice(template)} · by{' '}
                    {template.author}
                </p>
                <MediaSummary template={template} />
            </div>
            <div className="flex gap-2 sm:flex-col">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onPreview}
                >
                    <Eye /> Preview
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onChange}
                >
                    <LayoutGrid /> Change design
                </Button>
            </div>
        </div>
    );
}

type PriceFilter = '' | 'free' | 'paid';
type MediaFilter = '' | 'video' | 'music';

/**
 * Every template with filters (text, category, price, media). Used in the
 * picker's "Explore" modal and on the client's Templates page.
 */
export function TemplateGallery({
    templates,
    selectedId,
    onOpen,
    className,
}: {
    templates: Template[];
    selectedId?: string;
    onOpen: (template: Template) => void;
    className?: string;
}) {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [price, setPrice] = useState<PriceFilter>('');
    const [media, setMedia] = useState<MediaFilter>('');

    const categories = useMemo(
        () =>
            [
                ...new Map(
                    templates.map((t) => [t.category, t.category_label]),
                ),
            ].sort((a, b) => a[1].localeCompare(b[1])),
        [templates],
    );

    const query = search.trim().toLowerCase();
    const filtered = templates.filter(
        (t) =>
            (!query ||
                [t.name, t.author, t.description ?? '', ...t.tags].some(
                    (text) => text.toLowerCase().includes(query),
                )) &&
            (!category || t.category === category) &&
            (!price || (price === 'free') === t.is_free) &&
            (!media ||
                (media === 'video'
                    ? t.media_summary.videos > 0
                    : t.media_summary.music)),
    );

    const clearFilters = () => {
        setSearch('');
        setCategory('');
        setPrice('');
        setMedia('');
    };

    return (
        <div className={cn('flex flex-col', className)}>
            <div className="flex flex-wrap items-center gap-2 border-b p-4">
                <Input
                    className="min-w-48 flex-1"
                    placeholder="Search name, tag or author…"
                    aria-label="Search templates"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <Select
                    className="w-auto"
                    aria-label="Category"
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                >
                    <option value="">All categories</option>
                    {categories.map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </Select>
                <Select
                    className="w-auto"
                    aria-label="Price"
                    value={price}
                    onChange={(e) => setPrice(e.target.value as PriceFilter)}
                >
                    <option value="">Any price</option>
                    <option value="free">Free</option>
                    <option value="paid">Paid</option>
                </Select>
                <Select
                    className="w-auto"
                    aria-label="Media"
                    value={media}
                    onChange={(e) => setMedia(e.target.value as MediaFilter)}
                >
                    <option value="">Any media</option>
                    <option value="video">With video</option>
                    <option value="music">With music</option>
                </Select>
                <span className="text-xs text-muted-foreground">
                    {filtered.length} of {templates.length}
                </span>
            </div>
            <div className="p-4">
                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 py-16 text-center text-muted-foreground">
                        <SearchX className="size-8" />
                        <p>No templates match these filters.</p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={clearFilters}
                        >
                            Clear filters
                        </Button>
                    </div>
                ) : (
                    <TemplateGrid
                        templates={filtered}
                        selectedId={selectedId}
                        onOpen={onOpen}
                        className="lg:grid-cols-3 xl:grid-cols-4"
                    />
                )}
            </div>
        </div>
    );
}

/** Grid of template cards. Clicking a card opens its preview; the chosen one is marked. */
function TemplateGrid({
    templates,
    selectedId,
    onOpen,
    invalid,
    className,
}: {
    templates: Template[];
    selectedId?: string;
    onOpen: (template: Template) => void;
    invalid?: boolean;
    className?: string;
}) {
    return (
        <div
            role="list"
            aria-label="Invitation templates"
            aria-invalid={invalid}
            className={cn(
                'grid gap-3 sm:grid-cols-2 xl:grid-cols-3',
                className,
            )}
        >
            {templates.map((template) => {
                const selected = template.id === selectedId;

                return (
                    <button
                        key={template.id}
                        type="button"
                        role="listitem"
                        aria-current={selected || undefined}
                        aria-label={`Preview ${template.name}${selected ? ' (current design)' : ''}`}
                        onClick={() => onOpen(template)}
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
                            <span className="absolute inset-0 flex items-center justify-center bg-black/40 text-sm font-medium text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                                <Eye className="mr-1.5 size-4" /> Preview
                            </span>
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
                                    {templatePrice(template)}
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
