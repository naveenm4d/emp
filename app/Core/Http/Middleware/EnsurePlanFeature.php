<?php

namespace App\Core\Http\Middleware;

use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Models\Notification;
use App\Domains\Seating\Models\EventTable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `plan.feature:{feature}` on client routes: the event's owner must be on a
 * plan that includes the feature (e.g. Starter has no seating). The event is
 * found from the route's {event}, {table}, {guest} or {notification}.
 */
class EnsurePlanFeature
{
    public function __construct(
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $event = $this->event($request);

        if ($event !== null) {
            $this->plans->ensureFeature($event->client, PlanFeature::from($feature));
        }

        return $next($request);
    }

    private function event(Request $request): ?Event
    {
        foreach (['event', 'table', 'guest', 'notification'] as $parameter) {
            $value = $request->route($parameter);

            $event = match (true) {
                $value instanceof Event => $value,
                $value instanceof EventTable, $value instanceof Guest, $value instanceof Notification => $value->event,
                default => null,
            };

            if ($event !== null) {
                return $event;
            }
        }

        return null;
    }
}
