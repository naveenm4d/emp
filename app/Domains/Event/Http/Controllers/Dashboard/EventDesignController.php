<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The event's invitation design: its template's media slots, what was
 * uploaded, and a link to preview the generated invitation.
 */
class EventDesignController extends InertiaController
{
    public function __construct(
        private readonly EventDesignServiceInterface $designs,
        private readonly EventServiceInterface $events,
    ) {}

    public function show(Event $event): Response
    {
        $this->authorize('view', $event);
        $event->load(['templateVersion.template.latestVersion', 'media']);

        return Inertia::render('client/events/design', [
            'event' => EventResource::make($event),
            'design' => [
                'slots' => $this->designs->slots($event),
                'unused_media' => array_map(fn (EventMedia $media) => [
                    'id' => $media->id,
                    'slot_key' => $media->slot_key,
                    'type' => $media->type->value,
                    'url' => $media->url(),
                    'original_name' => $media->original_name,
                ], $this->designs->unusedMedia($event)),
                'missing' => $this->designs->missingRequiredSlots($event),
                'preview_url' => $event->previewUrl(),
                'fonts' => $event->templateVersion->fonts,
            ],
        ]);
    }

    public function upgrade(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event = $this->events->upgradeTemplate($event);

        return $this->backWithSuccess("Now using version {$event->templateVersion->version} of the template.");
    }
}
