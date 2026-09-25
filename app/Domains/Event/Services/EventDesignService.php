<?php

namespace App\Domains\Event\Services;

use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Template\DTOs\MediaSlot;
use App\Domains\Template\Enums\MediaType;
use App\Domains\Template\Support\PlaceholderContext;
use App\Domains\Template\Support\PlaceholderSyntax;
use App\Domains\Template\Support\TemplateRenderer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
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
        $event->load(['templateVersion', 'media']);
        $version = $event->templateVersion;

        $styles = $this->renderer->render(
            PlaceholderSyntax::parse($version->styles(), PlaceholderContext::Styles),
            assetUrl: $version->assetUrl(...),
        );

        $markup = $this->renderer->render(
            PlaceholderSyntax::parse($version->markup(), PlaceholderContext::Markup),
            [...$this->eventValues($event), ...$this->mediaValues($event)],
            $version->assetUrl(...),
            defer: self::GUEST_FIELDS,
        );

        $html = "<style>\n{$styles}\n</style>\n{$markup}";
        $hash = hash('sha256', $version->id."\n".$html);

        if ($hash === $event->rendered_hash && $event->rendered_path && $this->disk()->exists($event->rendered_path)) {
            return;
        }

        $path = "invitations/{$event->id}/{$hash}.html";
        $this->disk()->put($path, $html);

        $previous = $event->rendered_path;
        $this->events->recordRender($event, $path, $hash);

        if ($previous && $previous !== $path) {
            $this->disk()->delete($previous);
        }
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
            'fonts' => $version->fonts,
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
            'event.description' => $this->multiline($event->description),
            'event.type' => $event->event_type,
            'event.date' => $event->event_date?->translatedFormat('l, j F Y'),
            'event.start_time' => $this->time($event->start_time),
            'event.end_time' => $this->time($event->end_time),
            'event.location_name' => $event->location_name,
            'event.location_address' => $this->multiline($event->location_address),
            'event.map_url' => $event->map_url && preg_match('#^https?://#i', $event->map_url) ? $event->map_url : null,
        ];
    }

    /** @return array<string, string> slot key => public URL */
    private function mediaValues(Event $event): array
    {
        return $event->media
            ->mapWithKeys(fn (EventMedia $media) => [$media->slot_key => $media->url()])
            ->all();
    }

    private function multiline(?string $text): ?HtmlString
    {
        return filled($text) ? new HtmlString(nl2br(PlaceholderSyntax::escape((string) $text), false)) : null;
    }

    private function time(?string $time): ?string
    {
        return $time ? Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? "{$time}:00" : $time)?->format('g:i A') : null;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('emp.render_disk'));
    }
}
