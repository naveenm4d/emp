<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Http\Requests\Dashboard\ChangeTemplateRequest;
use App\Domains\Event\Http\Requests\Dashboard\UpdateDesignRequest;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventMedia;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Http\Resources\TemplateResource;
use Illuminate\Http\JsonResponse;
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

    public function show(Event $event, TemplateQueryServiceInterface $templates): Response
    {
        $this->authorize('view', $event);
        $event->load(['templateVersion.template.latestVersion', 'media', 'client']);
        $schema = $event->templateVersion->editable();

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
                'fonts' => $event->templateVersion->fonts(),
            ],
            'editor' => $this->designs->editorPreview($event),
            'templates' => TemplateResource::collection($templates->available($event->client)),
            'schema' => $schema->toArray(),
            'values' => $schema->resolve($event->customizations ?? []),
        ]);
    }

    /**
     * The editor's canvas with unsaved changes applied. Nothing is stored and
     * the guest invitation stays as it is until the client saves.
     */
    public function preview(UpdateDesignRequest $request, Event $event): JsonResponse
    {
        return response()->json($this->designs->editorPreview($event, $request->changes()));
    }

    /** Switch the event to another design (its latest version). Matching customisations carry over. */
    public function changeTemplate(ChangeTemplateRequest $request, Event $event): RedirectResponse
    {
        $event = $this->events->update($event, $request->toData());

        return $this->backWithSuccess("Now using {$event->templateVersion->template->name}.");
    }

    /** Save the editor's changes and regenerate the guest invitation. */
    public function update(UpdateDesignRequest $request, Event $event): RedirectResponse
    {
        $this->designs->customize($event, $request->changes());

        return $this->backWithSuccess('Invitation saved.');
    }

    public function upgrade(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event = $this->events->upgradeTemplate($event);

        return $this->backWithSuccess("Now using version {$event->templateVersion->version} of the template.");
    }
}
