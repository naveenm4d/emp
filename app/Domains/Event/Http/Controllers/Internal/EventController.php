<?php

namespace App\Domains\Event\Http\Controllers\Internal;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Http\Resources\EventResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends InertiaController
{
    public function __construct(
        private readonly EventQueryServiceInterface $events,
    ) {}

    public function index(Request $request): Response
    {
        $filters = EventFilters::fromArray($request->query());

        return Inertia::render('internal/events/index', [
            'events' => EventResource::collection($this->events->all($filters)),
            'filters' => $filters->toArray(),
            'states' => EventState::options(),
        ]);
    }
}
