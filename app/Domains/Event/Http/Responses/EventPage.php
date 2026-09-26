<?php

namespace App\Domains\Event\Http\Responses;

use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Http\Resources\PublicEventResource;
use App\Domains\Event\Http\Resources\PublicRegistrationFormResource;
use App\Domains\Event\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public event page behind the event's public URL (domain/{slug}/{code}):
 * the invitation design with self-registration, or, for guest-list-only
 * events, a notice pointing guests to their personal link.
 */
final class EventPage
{
    public static function render(Event $event, Request $request): Response
    {
        $publicEvent = PublicEventResource::make($event)->resolve($request);

        if (! $event->registration_type->hasPublicRegistration()) {
            return Inertia::render('web/events/guest-list-only', [
                'event' => $publicEvent,
            ])->withViewData([
                'meta' => [
                    'title' => $event->title,
                    'description' => 'This event is for invited guests only.',
                    'image' => null,
                ],
            ]);
        }

        // The event's invitation design, as guests see it (no guest name on the public page).
        $design = app(EventDesignServiceInterface::class)->forGuest($event, null);
        $event->loadMissing(['publicLink', 'registrationQuestions']);

        return Inertia::render('web/events/show', [
            'event' => $publicEvent,
            'design' => $design,
            'form' => PublicRegistrationFormResource::forRegistration($event)->resolve($request),
            'actions' => [
                'register' => route('web.events.register', ['slug' => $event->slug, 'code' => $event->publicLink?->code]),
            ],
        ])->withViewData([
            'meta' => [
                'title' => $event->title,
                'description' => Str::limit(strip_tags($event->description ?? "Join us at {$event->title}."), 160),
                'image' => $design['cover_image_url'],
            ],
        ]);
    }
}
