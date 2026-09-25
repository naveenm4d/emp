import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ExternalLink,
    Image,
    Music,
    Pencil,
    RefreshCw,
    Trash2,
    Upload,
    Video,
} from 'lucide-react';
import { useRef, useState } from 'react';

import { EventTabs } from '@/components/events/event-tabs';
import { PageHeader } from '@/components/shared/page-header';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientLayout from '@/layouts/client-layout';
import { edit } from '@/routes/client/events';
import { destroy, store } from '@/routes/client/events/media';
import { upgrade } from '@/routes/client/events/template';
import type {
    DesignSlot,
    Event,
    EventDesign,
    MediaType,
    Resource,
} from '@/types';

type Props = {
    event: Resource<Event>;
    design: EventDesign;
};

const accept: Record<MediaType, string> = {
    image: 'image/jpeg,image/png,image/webp,image/gif',
    video: 'video/mp4,video/webm',
    audio: 'audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg,audio/aac',
};

const icons = { image: Image, video: Video, audio: Music };

export default function EventDesignPage({
    event: { data: event },
    design,
}: Props) {
    const editable = event.state !== 'cancelled';
    const groups = (['image', 'video', 'audio'] as const)
        .map((type) => ({
            type,
            slots: design.slots.filter((slot) => slot.type === type),
        }))
        .filter((group) => group.slots.length > 0);

    return (
        <ClientLayout>
            <Head title={`Design · ${event.title}`} />
            <PageHeader
                title={event.title}
                description={
                    event.template && (
                        <span>
                            Template: {event.template.name} · v
                            {event.template.version}
                        </span>
                    )
                }
                actions={
                    <>
                        {editable && (
                            <Link
                                href={edit.url(event)}
                                className={buttonVariants({
                                    variant: 'outline',
                                })}
                            >
                                <Pencil /> Change template
                            </Link>
                        )}
                        <a
                            href={design.preview_url}
                            target="_blank"
                            rel="noreferrer"
                            className={buttonVariants()}
                        >
                            <ExternalLink /> Preview invitation
                        </a>
                    </>
                }
            />

            <EventTabs event={event} />

            <div className="mb-6 space-y-3">
                {event.template?.has_update && editable && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 p-3 text-sm">
                        <span>
                            Version {event.template.latest_version} of{' '}
                            {event.template.name} is available. Your invitation
                            keeps using v{event.template.version} until you
                            upgrade.
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
                            Upload {design.missing.join(', ')} before
                            publishing. The template needs{' '}
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

            {groups.length === 0 ? (
                <Card>
                    <CardContent className="py-10 text-center text-sm text-muted-foreground">
                        This template has no photos, videos or music to upload.
                        Your event details fill it in automatically.
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-6">
                    {groups.map((group) => (
                        <Card key={group.type}>
                            <CardHeader>
                                <CardTitle>
                                    {group.type === 'image'
                                        ? 'Photos'
                                        : group.type === 'video'
                                          ? 'Videos'
                                          : 'Music'}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {group.slots.map((slot) => (
                                    <SlotCard
                                        key={slot.key}
                                        event={event}
                                        slot={slot}
                                        editable={editable}
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            {design.unused_media.length > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Not used by this template</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <p className="text-muted-foreground">
                            These uploads belong to placeholders the current
                            template does not have. Guests don't see them.
                        </p>
                        {design.unused_media.map((media) => (
                            <div
                                key={media.id}
                                className="flex items-center justify-between gap-3 rounded-md border border-border px-3 py-2"
                            >
                                <span className="truncate">
                                    {media.original_name ?? media.slot_key}{' '}
                                    <span className="text-muted-foreground">
                                        ({media.slot_key})
                                    </span>
                                </span>
                                {editable && (
                                    <RemoveButton
                                        event={event}
                                        mediaId={media.id}
                                    />
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </ClientLayout>
    );
}

function SlotCard({
    event,
    slot,
    editable,
}: {
    event: Event;
    slot: DesignSlot;
    editable: boolean;
}) {
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
        <div className="flex flex-col overflow-hidden rounded-lg border border-border">
            <div className="flex aspect-video items-center justify-center bg-muted">
                {slot.media ? (
                    slot.type === 'image' ? (
                        <img
                            src={slot.media.url}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : slot.type === 'video' ? (
                        <video
                            src={slot.media.url}
                            controls
                            className="h-full w-full bg-black"
                        />
                    ) : (
                        <audio
                            src={slot.media.url}
                            controls
                            className="w-11/12"
                        />
                    )
                ) : (
                    <Icon className="size-8 text-muted-foreground/50" />
                )}
            </div>
            <div className="flex flex-1 flex-col gap-2 p-3">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-sm font-medium">{slot.label}</span>
                    {slot.required ? (
                        <Badge variant="secondary">Required</Badge>
                    ) : (
                        <Badge variant="outline">Optional</Badge>
                    )}
                </div>
                {slot.media && (
                    <span className="truncate text-xs text-muted-foreground">
                        {slot.media.original_name} ·{' '}
                        {(slot.media.size_bytes / 1024 / 1024).toFixed(1)} MB
                    </span>
                )}
                {error && <p className="text-xs text-destructive">{error}</p>}
                {editable && (
                    <div className="mt-auto flex gap-2">
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
                                ? `Uploading ${progress}%`
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

function RemoveButton({ event, mediaId }: { event: Event; mediaId: string }) {
    return (
        <Button
            size="sm"
            variant="ghost"
            aria-label="Remove"
            onClick={() => {
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
