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

    /**
     * Save the client's changes to the template's editable texts, colours and
     * sections (keys already validated against the schema) and re-render the
     * guest invitation. Values equal to the template's defaults are dropped.
     *
     * @param  array{texts?: array<string, string>, colors?: array<string, string>, sections?: array<string, bool>}  $changes
     */
    public function customize(Event $event, array $changes): Event;

    /**
     * The invitation for the editor's canvas: editable texts wrapped in
     * [data-emp-text], media marked with #emp-slot=… and empty image slots
     * shown as placeholders. $draft (unsaved editor changes, validated) is
     * applied on top of the saved customisations. Nothing is stored.
     *
     * @param  array{texts?: array<string, string>, colors?: array<string, string>, sections?: array<string, bool>}  $draft
     * @return array{html: string, fonts: list<string>, scripts: list<string>}
     */
    public function editorPreview(Event $event, array $draft = []): array;

    /** Fill the template with the event's details and media, and store the generated HTML. */
    public function render(Event $event): void;

    /**
     * The generated invitation for one viewer, with per-guest placeholders filled.
     *
     * @return array{html: string, fonts: list<string>, scripts: list<string>, has_bg_music: bool, cover_image_url: string|null, template: array{key: string, name: string, version: string}, rendered_at: string|null}
     */
    public function forGuest(Event $event, ?string $guestName): array;
}
