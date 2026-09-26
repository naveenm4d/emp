import { Head, router, useHttp, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ExternalLink,
    Image,
    LayoutGrid,
    Loader2,
    MousePointerClick,
    Music,
    RefreshCw,
    RotateCcw,
    Trash2,
    Upload,
    Video,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { EventTabs } from '@/components/events/event-tabs';
import { PageHeader } from '@/components/shared/page-header';
import { TemplateGallery } from '@/components/templates/template-picker';
import { TemplatePreviewDialog } from '@/components/templates/template-preview-dialog';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { InvitationFrame } from '@/components/web/invitation/invitation-frame';
import { RsvpActions } from '@/components/web/invitation/rsvp-actions';
import { useFonts } from '@/components/web/invitation/use-fonts';
import ClientLayout from '@/layouts/client-layout';
import { cn } from '@/lib/utils';
import {
    preview as previewDesign,
    template as changeDesign,
    update as updateDesign,
} from '@/routes/client/events/design';
import { preview as previewTemplate } from '@/routes/client/templates';
import { destroy, store } from '@/routes/client/events/media';
import { upgrade } from '@/routes/client/events/template';
import type {
    DesignSlot,
    Event,
    EventDesign,
    MediaType,
    Resource,
    Template,
} from '@/types';

/** What the template lets the client change (its template.json "editable" block). */
type Schema = {
    texts: Record<
        string,
        { label: string; default: string; multiline: boolean; max: number }
    >;
    colors: Record<string, { label: string; default: string }>;
    sections: Record<string, { label: string; default: boolean }>;
};

type Values = {
    texts: Record<string, string>;
    colors: Record<string, string>;
    sections: Record<string, boolean>;
};

type Props = {
    event: Resource<Event>;
    design: EventDesign;
    templates: { data: Template[] };
    editor: { html: string; fonts: string[]; scripts: string[] };
    schema: Schema;
    values: Values;
};

type Selection = { kind: 'text' | 'media'; key: string } | null;

const accept: Record<MediaType, string> = {
    image: 'image/jpeg,image/png,image/webp,image/gif',
    video: 'video/mp4,video/webm',
    audio: 'audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg,audio/aac',
};

const icons = { image: Image, video: Video, audio: Music };

const escapeHtml = (text: string) =>
    text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

/**
 * The invitation editor: the live invitation on the left, controls built
 * from the template's editable schema on the right. Clicking a text or a
 * photo in the invitation selects its control.
 */
export default function EventDesignPage(props: Props) {
    // A new design (switched or upgraded) brings a new schema and canvas:
    // start the editor fresh instead of carrying the old design's draft over.
    return (
        <DesignEditor key={props.event.data.template_version_id} {...props} />
    );
}

function DesignEditor({
    event: { data: event },
    design,
    templates,
    editor,
    schema,
    values,
}: Props) {
    const editable = event.state !== 'cancelled';
    useFonts(editor.fonts);

    const [root, setRoot] = useState<ShadowRoot | null>(null);
    const [browsing, setBrowsing] = useState(false);
    const [previewing, setPreviewing] = useState<Template | null>(null);
    const [selected, setSelected] = useState<Selection>(null);

    // Changes stay in this draft (and the canvas) until the client saves;
    // only saving stores them and regenerates the guest invitation.
    const [draft, setDraft] = useState<Values>(values);
    const [canvasHtml, setCanvasHtml] = useState(editor.html);
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const previewer = useHttp<Values, { html: string }>(values);
    const dirty = !sameValues(draft, values);

    const refreshCanvas = (next: Values) => {
        previewer.transform(() => next);
        previewer
            .post(previewDesign.url(event), {
                onSuccess: (response) => setCanvasHtml(response.html),
            })
            .catch(() => undefined);
    };

    // After a reload (save, upload, upgrade) show the server's canvas, or
    // re-apply unsaved changes on top of it.
    useEffect(() => {
        if (sameValues(draft, values)) {
            setCanvasHtml(editor.html);
        } else {
            refreshCanvas(draft);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editor.html]);

    // Warn before leaving with unsaved changes.
    useEffect(() => {
        if (!dirty || saving) {
            return;
        }

        const onBeforeUnload = (e: BeforeUnloadEvent) => e.preventDefault();
        const removeRouterGuard = router.on('before', (e) => {
            const visit = e.detail.visit;

            if (
                visit.method === 'get' &&
                !confirm(
                    'You have unsaved changes to your invitation. Leave without saving?',
                )
            ) {
                return false;
            }
        });

        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            removeRouterGuard();
        };
    }, [dirty, saving]);

    // Click-to-select in the canvas.
    useEffect(() => {
        if (!root) {
            return;
        }

        const onClick = (e: globalThis.Event) => {
            const target = e.target as Element;

            if (target.closest('a')) {
                e.preventDefault();
            }

            const text = target.closest<HTMLElement>('[data-emp-text]');
            const media = target.closest<HTMLElement>('[src*="#emp-slot="]');

            if (text) {
                setSelected({ kind: 'text', key: text.dataset.empText ?? '' });
            } else if (media) {
                const key = media.getAttribute('src')?.split('#emp-slot=')[1];
                setSelected(key ? { kind: 'media', key } : null);
            }
        };

        root.addEventListener('click', onClick);

        return () => root.removeEventListener('click', onClick);
    }, [root]);

    // Outline the selected element in the canvas.
    useEffect(() => {
        if (!root) {
            return;
        }

        root.querySelectorAll('[data-emp-selected]').forEach((el) =>
            el.removeAttribute('data-emp-selected'),
        );

        const selector =
            selected?.kind === 'text'
                ? `[data-emp-text="${selected.key}"]`
                : selected?.kind === 'media'
                  ? `[src*="#emp-slot=${selected.key}"]`
                  : null;

        if (selector) {
            root.querySelectorAll(selector).forEach((el) =>
                el.setAttribute('data-emp-selected', ''),
            );
        }
    }, [root, selected, canvasHtml]);

    // Colours set inline on the shadow host override the template's :host block.
    useEffect(() => {
        const host = root?.host as HTMLElement | undefined;

        Object.entries(draft.colors).forEach(([key, value]) =>
            host?.style.setProperty(`--emp-color-${key}`, value),
        );
    }, [root, draft.colors, canvasHtml]);

    const changeText = (key: string, value: string) => {
        setDraft((current) => ({
            ...current,
            texts: { ...current.texts, [key]: value },
        }));

        const html = schema.texts[key]?.multiline
            ? escapeHtml(value).replace(/\n/g, '<br>')
            : escapeHtml(value);
        root?.querySelectorAll(`[data-emp-text="${key}"]`).forEach((el) => {
            el.innerHTML = html;
        });
    };

    const changeColor = (key: string, value: string) =>
        setDraft((current) => ({
            ...current,
            colors: { ...current.colors, [key]: value },
        }));

    // Showing or hiding a section changes the layout: the server re-renders the canvas.
    const toggleSection = (key: string, on: boolean) => {
        const next = {
            ...draft,
            sections: { ...draft.sections, [key]: on },
        };
        setDraft(next);
        refreshCanvas(next);
    };

    const save = () =>
        router.patch(updateDesign.url(event), draft, {
            preserveScroll: true,
            onStart: () => {
                setSaving(true);
                setSaveError(null);
            },
            onError: (errors) =>
                setSaveError(
                    Object.values(errors)[0] ??
                        'Some changes could not be saved.',
                ),
            onFinish: () => setSaving(false),
        });

    const changeTemplate = (template: Template) => {
        if (
            dirty &&
            !confirm(
                'Switching design discards your unsaved changes. Texts and colours with the same name carry over. Continue?',
            )
        ) {
            return;
        }

        router.patch(
            changeDesign.url(event),
            { template_id: template.id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPreviewing(null);
                    setBrowsing(false);
                    setDraft(values);
                },
            },
        );
    };

    const discard = () => {
        setDraft(values);
        setCanvasHtml(editor.html);
        setSaveError(null);
    };

    const mediaSlots = design.slots.filter((slot) => slot.type !== 'audio');
    const audioSlots = design.slots.filter((slot) => slot.type === 'audio');
    const textKeys = Object.keys(schema.texts);
    const colorKeys = Object.keys(schema.colors);
    const sectionKeys = Object.keys(schema.sections);

    return (
        <ClientLayout>
            <Head title={`Invitation · ${event.title}`} />
            <PageHeader
                title={event.title}
                description={
                    event.template && (
                        <span className="flex flex-wrap items-center gap-2">
                            Design: {event.template.name} · v
                            {event.template.version}
                        </span>
                    )
                }
                actions={
                    <>
                        {editable && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setBrowsing(true)}
                            >
                                <LayoutGrid /> Change design
                            </Button>
                        )}
                        <a
                            href={design.preview_url}
                            target="_blank"
                            rel="noreferrer"
                            className={buttonVariants()}
                        >
                            <ExternalLink /> Preview as guest
                        </a>
                    </>
                }
            />

            <EventTabs event={event} />

            <Notices event={event} design={design} editable={editable} />

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                <Card className="overflow-hidden p-0 lg:sticky lg:top-4">
                    <div className="flex items-center gap-2 border-b px-4 py-2 text-xs text-muted-foreground">
                        <MousePointerClick className="size-3.5" />
                        Click text or a photo in the invitation to edit it.
                    </div>
                    <div className="max-h-[calc(100dvh-9rem)] overflow-y-auto bg-white">
                        <InvitationFrame
                            html={canvasHtml}
                            scripts={editor.scripts}
                            onReady={setRoot}
                            rsvp={
                                <RsvpActions
                                    mode="preview"
                                    rsvp={null}
                                    actions={null}
                                />
                            }
                        />
                    </div>
                </Card>

                <div className="space-y-4">
                    {editable && (
                        <SaveBar
                            dirty={dirty}
                            saving={saving}
                            refreshing={previewer.processing}
                            error={saveError}
                            onSave={save}
                            onDiscard={discard}
                        />
                    )}

                    {textKeys.length > 0 && (
                        <Panel title="Text">
                            {textKeys.map((key) => (
                                <TextControl
                                    key={key}
                                    field={schema.texts[key]}
                                    value={draft.texts[key] ?? ''}
                                    selected={
                                        selected?.kind === 'text' &&
                                        selected.key === key
                                    }
                                    disabled={!editable}
                                    onFocus={() =>
                                        setSelected({ kind: 'text', key })
                                    }
                                    onChange={(value) => changeText(key, value)}
                                />
                            ))}
                        </Panel>
                    )}

                    {mediaSlots.length > 0 && (
                        <Panel title="Photos & videos">
                            {mediaSlots.map((slot) => (
                                <SlotControl
                                    key={slot.key}
                                    event={event}
                                    slot={slot}
                                    editable={editable}
                                    selected={
                                        selected?.kind === 'media' &&
                                        selected.key === slot.key
                                    }
                                    onSelect={() =>
                                        setSelected({
                                            kind: 'media',
                                            key: slot.key,
                                        })
                                    }
                                />
                            ))}
                        </Panel>
                    )}

                    {colorKeys.length > 0 && (
                        <Panel title="Colours">
                            {colorKeys.map((key) => (
                                <ColorControl
                                    key={key}
                                    field={schema.colors[key]}
                                    value={draft.colors[key] ?? ''}
                                    disabled={!editable}
                                    onChange={(value) =>
                                        changeColor(key, value)
                                    }
                                />
                            ))}
                        </Panel>
                    )}

                    {sectionKeys.length > 0 && (
                        <Panel title="Sections">
                            {sectionKeys.map((key) => (
                                <label
                                    key={key}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    {schema.sections[key].label}
                                    <input
                                        type="checkbox"
                                        role="switch"
                                        className="size-4 accent-primary"
                                        checked={draft.sections[key]}
                                        disabled={!editable}
                                        onChange={(e) =>
                                            toggleSection(key, e.target.checked)
                                        }
                                    />
                                </label>
                            ))}
                        </Panel>
                    )}

                    {audioSlots.length > 0 && (
                        <Panel title="Music">
                            {audioSlots.map((slot) => (
                                <SlotControl
                                    key={slot.key}
                                    event={event}
                                    slot={slot}
                                    editable={editable}
                                    selected={false}
                                    onSelect={() => undefined}
                                />
                            ))}
                        </Panel>
                    )}

                    {design.unused_media.length > 0 && (
                        <Panel title="Not used by this design">
                            <p className="text-xs text-muted-foreground">
                                These uploads belong to places the current
                                design does not have. Guests don't see them.
                            </p>
                            {design.unused_media.map((media) => (
                                <div
                                    key={media.id}
                                    className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2 text-sm"
                                >
                                    <span className="truncate">
                                        {media.original_name ?? media.slot_key}
                                    </span>
                                    {editable && (
                                        <RemoveButton
                                            event={event}
                                            mediaId={media.id}
                                        />
                                    )}
                                </div>
                            ))}
                        </Panel>
                    )}
                </div>
            </div>
            <Dialog open={browsing} onOpenChange={setBrowsing}>
                <DialogContent className="h-[calc(100dvh-2rem)] max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Change design</DialogTitle>
                        <DialogDescription>
                            Click a design to preview it with this event's
                            details. Your uploads are kept.
                        </DialogDescription>
                    </DialogHeader>
                    <TemplateGallery
                        templates={templates.data}
                        selectedId={event.template?.id}
                        onOpen={setPreviewing}
                        className="flex-1 overflow-y-auto"
                    />
                </DialogContent>
            </Dialog>
            <TemplatePreviewDialog
                template={previewing}
                previewUrl={(template) => previewTemplate.url(template)}
                details={{
                    title: event.title,
                    description: event.description ?? '',
                    event_type: event.event_type ?? '',
                    event_date: event.event_date ?? '',
                    start_time: event.start_time ?? '',
                    end_time: event.end_time ?? '',
                    location_name: event.location_name ?? '',
                    location_address: event.location_address ?? '',
                }}
                onClose={() => setPreviewing(null)}
                action={{
                    label:
                        previewing?.id === event.template?.id
                            ? 'Current design'
                            : 'Use this design',
                    disabled: previewing?.id === event.template?.id,
                    onClick: changeTemplate,
                }}
            />
        </ClientLayout>
    );
}

/** Whether two sets of editor values are the same. */
function sameValues(a: Values, b: Values): boolean {
    return (['texts', 'colors', 'sections'] as const).every((kind) =>
        Object.keys({ ...a[kind], ...b[kind] }).every(
            (key) =>
                String(a[kind][key]).toLowerCase() ===
                String(b[kind][key]).toLowerCase(),
        ),
    );
}

function SaveBar({
    dirty,
    saving,
    refreshing,
    error,
    onSave,
    onDiscard,
}: {
    dirty: boolean;
    saving: boolean;
    refreshing: boolean;
    error: string | null;
    onSave: () => void;
    onDiscard: () => void;
}) {
    return (
        <Card
            className={cn(
                'sticky top-4 z-10 p-4',
                dirty && 'ring-2 ring-primary/40',
            )}
        >
            <div className="flex items-center justify-between gap-3">
                <p className="flex items-center gap-2 text-sm">
                    {refreshing ? (
                        <>
                            <Loader2 className="size-4 animate-spin text-muted-foreground" />
                            Updating preview…
                        </>
                    ) : dirty ? (
                        <span className="font-medium">Unsaved changes</span>
                    ) : (
                        <>
                            <CheckCircle2 className="size-4 text-emerald-600" />
                            <span className="text-muted-foreground">
                                Guests see the saved version.
                            </span>
                        </>
                    )}
                </p>
                <div className="flex gap-2">
                    {dirty && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={saving}
                            onClick={onDiscard}
                        >
                            Discard
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        disabled={!dirty || saving}
                        onClick={onSave}
                    >
                        {saving && <Loader2 className="animate-spin" />}
                        {saving ? 'Saving…' : 'Save'}
                    </Button>
                </div>
            </div>
            {error && <p className="mt-2 text-xs text-destructive">{error}</p>}
        </Card>
    );
}

function Panel({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">{children}</CardContent>
        </Card>
    );
}

function TextControl({
    field,
    value,
    selected,
    disabled,
    onFocus,
    onChange,
}: {
    field: Schema['texts'][string];
    value: string;
    selected: boolean;
    disabled: boolean;
    onFocus: () => void;
    onChange: (value: string) => void;
}) {
    const ref = useRef<HTMLInputElement & HTMLTextAreaElement>(null);

    useEffect(() => {
        if (selected && document.activeElement !== ref.current) {
            ref.current?.scrollIntoView({
                block: 'center',
                behavior: 'smooth',
            });
            ref.current?.focus({ preventScroll: true });
        }
    }, [selected]);

    const props = {
        ref,
        value,
        disabled,
        maxLength: field.max,
        onFocus,
        onChange: (
            e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
        ) => onChange(e.target.value),
        className: cn(selected && 'ring-2 ring-primary/50'),
    };

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between gap-2">
                <span className="text-sm font-medium">{field.label}</span>
                {value !== field.default && !disabled && (
                    <button
                        type="button"
                        className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        onClick={() => onChange(field.default)}
                    >
                        <RotateCcw className="size-3" /> Reset
                    </button>
                )}
            </div>
            {field.multiline ? (
                <Textarea rows={3} {...props} />
            ) : (
                <Input {...props} />
            )}
        </div>
    );
}

function ColorControl({
    field,
    value,
    disabled,
    onChange,
}: {
    field: Schema['colors'][string];
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="flex items-center gap-3">
            <input
                type="color"
                aria-label={field.label}
                className="size-9 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-0.5 disabled:cursor-not-allowed"
                value={/^#[0-9a-fA-F]{6}$/.test(value) ? value : field.default}
                disabled={disabled}
                onChange={(e) => onChange(e.target.value)}
            />
            <div className="flex-1">
                <p className="text-sm font-medium">{field.label}</p>
                <Input
                    className="mt-1 h-7 font-mono text-xs"
                    value={value}
                    maxLength={7}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.value)}
                />
            </div>
            {value.toLowerCase() !== field.default && !disabled && (
                <button
                    type="button"
                    aria-label={`Reset ${field.label}`}
                    className="text-muted-foreground hover:text-foreground"
                    onClick={() => onChange(field.default)}
                >
                    <RotateCcw className="size-3.5" />
                </button>
            )}
        </div>
    );
}

function SlotControl({
    event,
    slot,
    editable,
    selected,
    onSelect,
}: {
    event: Event;
    slot: DesignSlot;
    editable: boolean;
    selected: boolean;
    onSelect: () => void;
}) {
    const container = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLInputElement>(null);
    const [progress, setProgress] = useState<number | null>(null);
    const errors = usePage().props.errors as Record<
        string,
        Record<string, string> | string
    >;
    const bag = errors[slot.key];
    const error =
        typeof bag === 'object' ? (bag.file ?? bag.slot_key) : undefined;
    const Icon = icons[slot.type];

    useEffect(() => {
        if (selected) {
            container.current?.scrollIntoView({
                block: 'center',
                behavior: 'smooth',
            });
        }
    }, [selected]);

    const upload = (file: File) =>
        router.post(
            store.url(event),
            { slot_key: slot.key, file },
            {
                forceFormData: true,
                preserveScroll: true,
                errorBag: slot.key,
                onProgress: (event) => setProgress(event?.percentage ?? null),
                onFinish: () => {
                    setProgress(null);
                    if (input.current) {
                        input.current.value = '';
                    }
                },
            },
        );

    return (
        <div
            ref={container}
            onClick={onSelect}
            className={cn(
                'flex gap-3 rounded-lg border p-2 transition-colors',
                selected
                    ? 'border-primary ring-1 ring-primary'
                    : 'border-border',
            )}
        >
            <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted">
                {slot.media && slot.type === 'image' ? (
                    <img
                        src={slot.media.url}
                        alt=""
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <Icon
                        className={cn(
                            'size-6',
                            slot.media
                                ? 'text-primary'
                                : 'text-muted-foreground/50',
                        )}
                    />
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                <div className="flex items-center justify-between gap-2">
                    <span className="truncate text-sm font-medium">
                        {slot.label}
                    </span>
                    {slot.required ? (
                        <Badge variant="secondary">Required</Badge>
                    ) : (
                        <Badge variant="outline">Optional</Badge>
                    )}
                </div>
                {slot.media && (
                    <span className="truncate text-xs text-muted-foreground">
                        {slot.media.original_name}
                    </span>
                )}
                {error && <p className="text-xs text-destructive">{error}</p>}
                {editable && (
                    <div className="flex gap-2">
                        <input
                            ref={input}
                            type="file"
                            accept={accept[slot.type]}
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    upload(file);
                                }
                            }}
                        />
                        <Button
                            size="sm"
                            variant={slot.media ? 'outline' : 'default'}
                            disabled={progress !== null}
                            onClick={() => input.current?.click()}
                        >
                            <Upload />
                            {progress !== null
                                ? `${progress}%`
                                : slot.media
                                  ? 'Replace'
                                  : 'Upload'}
                        </Button>
                        {slot.media && (
                            <RemoveButton
                                event={event}
                                mediaId={slot.media.id}
                            />
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function Notices({
    event,
    design,
    editable,
}: {
    event: Event;
    design: EventDesign;
    editable: boolean;
}) {
    return (
        <div className="mb-6 space-y-3">
            {event.template?.has_update && editable && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 p-3 text-sm">
                    <span>
                        Version {event.template.latest_version} of{' '}
                        {event.template.name} is available. Your invitation
                        keeps using v{event.template.version} until you upgrade.
                    </span>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            router.post(
                                upgrade.url(event),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RefreshCw /> Upgrade to v
                        {event.template.latest_version}
                    </Button>
                </div>
            )}

            {design.missing.length > 0 ? (
                <div className="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Upload {design.missing.join(', ')} before publishing.
                        The design needs{' '}
                        {design.missing.length === 1 ? 'it' : 'them'}.
                    </span>
                </div>
            ) : (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <CheckCircle2 className="size-4 text-emerald-600" />
                    Your invitation has everything it needs.
                </div>
            )}
        </div>
    );
}

function RemoveButton({ event, mediaId }: { event: Event; mediaId: string }) {
    return (
        <Button
            size="sm"
            variant="ghost"
            aria-label="Remove"
            onClick={(e) => {
                e.stopPropagation();

                if (confirm('Remove this file from your invitation?')) {
                    router.delete(destroy.url({ event, media: mediaId }), {
                        preserveScroll: true,
                    });
                }
            }}
        >
            <Trash2 />
        </Button>
    );
}
