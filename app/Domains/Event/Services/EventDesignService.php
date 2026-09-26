<?php

namespace App\Domains\Event\Services;

use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\Events\EventDesignChanged;
use App\Domains\Event\Exceptions\EventNotEditableException;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Template\DTOs\MediaSlot;
use App\Domains\Template\Enums\MediaType;
use App\Domains\Template\Support\PlaceholderContext;
use App\Domains\Template\Support\PlaceholderFormat;
use App\Domains\Template\Support\PlaceholderImage;
use App\Domains\Template\Support\PlaceholderSyntax;
use App\Domains\Template\Support\TemplateRenderer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Turns an event's template code into its invitation, WordPress-theme style:
 * the code is fixed, the client's details and uploads fill the placeholders.
 *
 *  1. render()   on save: event + media placeholders are filled and the
 *                result is written to the render disk. guest.* is left open.
 *  2. forGuest() per view: the stored file with guest.* filled in.
 */
class EventDesignService implements EventDesignServiceInterface
{
    private const GUEST_FIELDS = PlaceholderSyntax::GUEST_FIELDS;

    /** Outlines what can be clicked in the editor's canvas. */
    private const EDITOR_STYLES = <<<'CSS'
        [data-emp-text] { cursor: text; border-radius: 3px; outline: 2px dashed transparent; outline-offset: 3px; transition: outline-color .15s; }
        [data-emp-text]:hover { outline-color: rgba(99, 102, 241, .55); }
        [src*="#emp-slot="] { cursor: pointer; outline: 3px solid transparent; outline-offset: -3px; transition: outline-color .15s; }
        [src*="#emp-slot="]:hover { outline-color: rgba(99, 102, 241, .55); }
        [data-emp-selected] { outline: 2px solid #6366f1 !important; }
        CSS;

    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly TemplateRenderer $renderer,
    ) {}

    public function slots(Event $event): array
    {
        $media = $event->media->keyBy('slot_key');

        return array_map(function (MediaSlot $slot) use ($media) {
            /** @var EventMedia|null $file */
            $file = $media->get($slot->key);

            return [
                ...$slot->toArray(),
                'media' => $file ? [
                    'id' => $file->id,
                    'url' => $file->url(),
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                    'size_bytes' => $file->size_bytes,
                ] : null,
            ];
        }, $event->templateVersion->slots());
    }

    public function unusedMedia(Event $event): array
    {
        $keys = array_map(fn (MediaSlot $slot) => $slot->key, $event->templateVersion->slots());

        return array_values($event->media->reject(fn (EventMedia $media) => in_array($media->slot_key, $keys, true))->all());
    }

    public function missingRequiredSlots(Event $event): array
    {
        $uploaded = $event->media->pluck('slot_key')->all();

        return array_values(array_map(
            fn (MediaSlot $slot) => $slot->displayLabel(),
            array_filter(
                $event->templateVersion->slots(),
                fn (MediaSlot $slot) => $slot->required && ! in_array($slot->key, $uploaded, true),
            ),
        ));
    }

    public function render(Event $event): void
    {
        $event->load(['templateVersion.template', 'media']);
        $version = $event->templateVersion;
        $editable = $version->editable();
        $customizations = $event->customizations ?? [];

        $markup = $this->renderer->render(
            PlaceholderSyntax::parse($version->markup(), PlaceholderContext::Markup),
            [...$this->eventValues($event), ...$this->mediaValues($event), ...$editable->values($customizations)],
            $version->assetUrl(...),
            defer: self::GUEST_FIELDS,
        );

        $html = "<style>\n{$this->styles($event)}\n</style>\n{$markup}";
        $hash = hash('sha256', $version->id."\n".$html);

        if ($hash === $event->rendered_hash && $event->rendered_path && $this->disk()->exists($event->rendered_path)) {
            return;
        }

        $path = "invitations/{$event->storageDirectory()}/{$hash}.html";
        $this->disk()->put($path, $html);

        $previous = $event->rendered_path;
        $this->events->recordRender($event, $path, $hash);

        if ($previous && $previous !== $path) {
            $this->disk()->delete($previous);
        }
    }

    public function customize(Event $event, array $changes): Event
    {
        if (! $event->state->isEditable()) {
            throw new EventNotEditableException;
        }

        /** @var Event $event */
        $event = $this->events->update($event, ['customizations' => $this->withChanges($event, $changes)]);

        EventDesignChanged::dispatch($event);

        return $event;
    }

    public function editorPreview(Event $event, array $draft = []): array
    {
        $event->loadMissing(['templateVersion.template', 'media']);
        $version = $event->templateVersion;
        $customizations = $this->withChanges($event, $draft);

        // Each slot's element is findable by a URL fragment, which does not change what loads.
        $media = collect($version->slots())
            ->mapWithKeys(fn (MediaSlot $slot) => [$slot->key => $slot->type === MediaType::Image
                ? PlaceholderImage::dataUri($slot->displayLabel())
                : null])
            ->merge($this->mediaValues($event))
            ->map(fn (?string $url, string $key) => $url === null ? null : "{$url}#emp-slot={$key}")
            ->all();

        $markup = $this->renderer->render(
            PlaceholderSyntax::parse($version->markup(), PlaceholderContext::Markup),
            [
                ...$this->eventValues($event),
                ...$media,
                ...$version->editable()->values($customizations),
                'guest.name' => 'Guest Name',
            ],
            $version->assetUrl(...),
            decorate: fn (string $name, string $html, bool $insideTag) => str_starts_with($name, 'text.') && ! $insideTag
                ? '<span data-emp-text="'.substr($name, 5).'">'.$html.'</span>'
                : $html,
        );

        return [
            'html' => "<style>\n{$this->styles($event, $customizations)}\n".self::EDITOR_STYLES."\n</style>\n{$markup}",
            'fonts' => $version->fonts(),
            'scripts' => $version->scriptUrls(),
        ];
    }

    public function forGuest(Event $event, ?string $guestName): array
    {
        $event->loadMissing(['templateVersion.template', 'media']);

        if (! $event->rendered_path || ! $this->disk()->exists($event->rendered_path)) {
            $this->render($event);
        }

        $html = $this->renderer->render(
            PlaceholderSyntax::parse((string) $this->disk()->get((string) $event->rendered_path), PlaceholderContext::Guest),
            ['guest.name' => $guestName],
        );

        $version = $event->templateVersion;
        $slots = collect($version->slots())->keyBy('key');
        $media = $event->media->filter(fn (EventMedia $media) => $slots->has($media->slot_key));
        $cover = $media
            ->filter(fn (EventMedia $media) => $media->type === MediaType::Image)
            ->sortBy(fn (EventMedia $media) => (int) substr($media->slot_key, 4))
            ->first();

        return [
            'html' => $html,
            'fonts' => $version->fonts(),
            'scripts' => $version->scriptUrls(),
            'has_bg_music' => $media->contains('slot_key', 'bg_music'),
            'cover_image_url' => $cover?->url(),
            'template' => [
                'key' => $version->template->key,
                'name' => $version->template->name,
                'version' => $version->version,
            ],
            'rendered_at' => $event->rendered_at?->toIso8601String(),
        ];
    }

    /** @return array<string, string|HtmlString|null> */
    private function eventValues(Event $event): array
    {
        return [
            'event.title' => $event->title,
            'event.description' => PlaceholderFormat::multiline($event->description),
            'event.type' => $event->event_type?->label(),
            'event.date' => PlaceholderFormat::date($event->event_date),
            'event.start_time' => PlaceholderFormat::time($event->start_time),
            'event.end_time' => PlaceholderFormat::time($event->end_time),
            'event.location_name' => $event->location_name,
            'event.location_address' => PlaceholderFormat::multiline($event->location_address),
            'event.map_url' => PlaceholderFormat::url($event->map_url),
        ];
    }

    /** @return array<string, string> slot key => public URL */
    private function mediaValues(Event $event): array
    {
        return $event->media
            ->mapWithKeys(fn (EventMedia $media) => [$media->slot_key => $media->url()])
            ->all();
    }

    /**
     * The event's saved customisations with $changes applied. Only differences
     * from the template's defaults are kept.
     *
     * @param  array{texts?: array<string, string>, colors?: array<string, string>, sections?: array<string, bool>}  $changes
     * @return array{texts?: array<string, string>, colors?: array<string, string>, sections?: array<string, bool>}
     */
    private function withChanges(Event $event, array $changes): array
    {
        $schema = $event->templateVersion->editable()->toArray();
        $customizations = $event->customizations ?? [];

        foreach (['texts', 'colors', 'sections'] as $kind) {
            foreach ($changes[$kind] ?? [] as $key => $value) {
                $value = $kind === 'colors' ? strtolower((string) $value) : $value;

                if ($value === ($schema[$kind][$key]['default'] ?? null)) {
                    unset($customizations[$kind][$key]);
                } else {
                    $customizations[$kind][$key] = $value;
                }
            }

            if (($customizations[$kind] ?? null) === []) {
                unset($customizations[$kind]);
            }
        }

        return $customizations;
    }

    /**
     * The template's CSS with the event's colours (var(--emp-color-*)) in front.
     *
     * @param  array<string, mixed>|null  $customizations  defaults to the saved ones
     */
    private function styles(Event $event, ?array $customizations = null): string
    {
        $version = $event->templateVersion;

        return $version->editable()->colorStyles($customizations ?? $event->customizations ?? []).$this->renderer->render(
            PlaceholderSyntax::parse($version->styles(), PlaceholderContext::Styles),
            assetUrl: $version->assetUrl(...),
        );
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('emp.render_disk'));
    }
}
