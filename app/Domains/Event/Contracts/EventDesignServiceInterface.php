<?php

namespace App\Domains\Event\Contracts;

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;

interface EventDesignServiceInterface
{
    /**
     * The media slots of the event's pinned template version, with what was uploaded.
     *
     * @return list<array{key: string, type: string, required: bool, label: string, media: array{id: string, url: string, original_name: string|null, mime_type: string, size_bytes: int}|null}>
     */
    public function slots(Event $event): array;

    /** @return list<EventMedia> uploads in slots the current template version does not have */
    public function unusedMedia(Event $event): array;

    /** @return list<string> labels of required slots with nothing uploaded */
    public function missingRequiredSlots(Event $event): array;

    /** Fill the template with the event's details and media, and store the generated HTML. */
    public function render(Event $event): void;

    /**
     * The generated invitation for one viewer, with per-guest placeholders filled.
     *
     * @return array{html: string, fonts: list<string>, has_bg_music: bool, cover_image_url: string|null, template: array{key: string, name: string, version: string}, rendered_at: string|null}
     */
    public function forGuest(Event $event, ?string $guestName): array;
}
