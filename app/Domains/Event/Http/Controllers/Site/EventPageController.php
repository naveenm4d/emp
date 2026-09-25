<?php

namespace App\Domains\Event\Http\Controllers\Site;

use App\Core\Http\Controllers\Controller;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Http\Resources\PublicEventResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** The public page of a published event (/e/{slug}) with self-registration. */
class EventPageController extends Controller
{
    public function __construct(
        private readonly EventQueryServiceInterface $events,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $event = $this->events->findPublishedBySlug($slug);

        return Inertia::render('site/events/show', [
            'event' => PublicEventResource::make($event)->resolve($request),
            'actions' => [
                'register' => route('site.events.register', $event->slug),
            ],
        ])->withViewData([
            'meta' => [
                'title' => $event->title,
                'description' => Str::limit(strip_tags($event->description ?? "Join us at {$event->title}."), 160),
                'image' => null,
            ],
        ]);
    }
}
