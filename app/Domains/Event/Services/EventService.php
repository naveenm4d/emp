<?php

namespace App\Domains\Event\Services;

use App\Core\Services\BaseService;
use App\Domains\Client\Models\Client;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\DTOs\CreateEventData;
use App\Domains\Event\DTOs\UpdateEventData;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Events\EventDesignChanged;
use App\Domains\Event\Events\EventRegistrationTypeChanged;
use App\Domains\Event\Exceptions\EventDesignIncompleteException;
use App\Domains\Event\Exceptions\EventNotEditableException;
use App\Domains\Event\Exceptions\EventSlugReservedException;
use App\Domains\Event\Exceptions\InvalidEventStateTransitionException;
use App\Domains\Event\Exceptions\RegistrationNotAvailableException;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use Illuminate\Support\Str;

class EventService extends BaseService implements EventServiceInterface
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly TemplateQueryServiceInterface $templates,
        private readonly EventDesignServiceInterface $designs,
        private readonly EventLinkServiceInterface $links,
    ) {}

    public function create(Client $client, CreateEventData $data): Event
    {
        $slug = $this->normalizeSlug($data->slug);

        // The event is pinned to the template's current version.
        $template = $this->templates->findSelectable($data->template_id, $client);

        return $this->transaction(function () use ($data, $slug, $client, $template) {
            /** @var Event $event */
            $event = $this->events->create([
                ...collect($data->toArray())->except('template_id')->all(),
                'template_version_id' => $template->latest_version_id,
                'slug' => $slug,
                'client_id' => $client->id,
                'state' => EventState::Draft,
                'registration_open' => false,
            ]);

            // The event's public URL (domain/{slug}/{code}).
            $this->links->createForEvent($event);

            EventDesignChanged::dispatch($event);

            return $event;
        });
    }

    public function update(Event $event, UpdateEventData $data): Event
    {
        $this->ensureEditable($event);

        $attributes = $data->except(['template_id']);

        if ($data->has('slug')) {
            $attributes['slug'] = $this->normalizeSlug((string) $data->get('slug'));
        }

        // Switching to another template pins its current version. Picking the
        // same template again keeps the pinned version (see upgradeTemplate).
        if ($data->has('template_id') && $data->get('template_id') !== $event->templateVersion->template_id) {
            $template = $this->templates->findSelectable((string) $data->get('template_id'), $event->client);
            $attributes['template_version_id'] = $template->latest_version_id;
        }

        // A guest-list-only event has no public registration to keep open.
        if ($data->has('registration_type') && ! RegistrationType::from((string) $data->get('registration_type'))->hasPublicRegistration()) {
            $attributes['registration_open'] = false;
        }

        $previousType = $event->registration_type;

        return $this->transaction(function () use ($event, $attributes, $previousType) {
            /** @var Event $event */
            $event = $this->events->update($event, $attributes);
            $event->unsetRelation('templateVersion');

            if ($event->registration_type !== $previousType) {
                EventRegistrationTypeChanged::dispatch($event, $previousType);
            }

            EventDesignChanged::dispatch($event);

            return $event;
        });
    }

    public function upgradeTemplate(Event $event): Event
    {
        $this->ensureEditable($event);

        $template = $this->templates->findSelectable($event->templateVersion->template_id, $event->client);

        if ($template->latest_version_id === $event->template_version_id) {
            return $event;
        }

        return $this->transaction(function () use ($event, $template) {
            /** @var Event $event */
            $event = $this->events->update($event, ['template_version_id' => $template->latest_version_id]);
            $event->unsetRelation('templateVersion');

            EventDesignChanged::dispatch($event);

            return $event;
        });
    }

    public function transition(Event $event, EventState $state): Event
    {
        if ($event->state === $state) {
            return $event;
        }

        if (! $event->state->canTransitionTo($state)) {
            throw InvalidEventStateTransitionException::between($event->state, $state);
        }

        // Guests must never see an invitation with empty required media.
        if ($state === EventState::Published && ($missing = $this->designs->missingRequiredSlots($event)) !== []) {
            throw EventDesignIncompleteException::missing($missing);
        }

        $attributes = ['state' => $state];

        // A cancelled event can never accept registrations.
        if ($state === EventState::Cancelled) {
            $attributes['registration_open'] = false;
        }

        /** @var Event */
        return $this->events->update($event, $attributes);
    }

    public function openRegistration(Event $event): Event
    {
        $this->ensureEditable($event);

        if (! $event->registration_type->hasPublicRegistration()) {
            throw new RegistrationNotAvailableException;
        }

        /** @var Event */
        return $this->events->update($event, ['registration_open' => true]);
    }

    public function closeRegistration(Event $event): Event
    {
        /** @var Event */
        return $this->events->update($event, ['registration_open' => false]);
    }

    public function delete(Event $event): void
    {
        $this->events->delete($event);
    }

    /** Slugs are labels in the event's links (the code identifies the event), so they need not be unique. */
    private function normalizeSlug(string $slug): string
    {
        $slug = Str::slug(mb_strtolower(trim($slug)));

        if (in_array($slug, Event::RESERVED_SLUGS, true)) {
            throw new EventSlugReservedException;
        }

        return $slug;
    }

    private function ensureEditable(Event $event): void
    {
        if (! $event->state->isEditable()) {
            throw new EventNotEditableException;
        }
    }
}
