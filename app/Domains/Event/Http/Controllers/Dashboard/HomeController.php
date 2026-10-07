<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\Concerns\ResolvesClient;
use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventPeriod;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The client's home: the next event, what needs attention and the rest coming up. */
class HomeController extends InertiaController
{
    use ResolvesClient;

    public function __invoke(Request $request, EventQueryServiceInterface $events): Response
    {
        $user = $this->clientUser($request);
        $upcoming = $events->forClient($user->client, new EventFilters(period: EventPeriod::Upcoming), $user);
        $next = collect($upcoming->items())->first(fn (Event $event) => $event->state === EventState::Published);
        // Its invitation's thumbnail leads the Next up card.
        $next?->load('templateVersion.template.latestVersion');

        return Inertia::render('client/home', [
            'events' => EventResource::collection($upcoming),
            // The soonest live event; drafts and cancelled events never lead.
            'next' => $next ? EventResource::make($next) : null,
            'totals' => $events->totalsForClient($user->client, $user),
        ]);
    }
}
