<?php

namespace App\Domains\Notification\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Http\Resources\NotificationResource;
use App\Domains\Notification\Models\Notification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends InertiaController
{
    public function __construct(
        private readonly NotificationQueryServiceInterface $notifications,
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function index(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);
        // The event header's poster shows the invitation's image.
        $event->loadMissing('templateVersion.template.latestVersion');

        // Not in the plan: the tab shows an upgrade prompt, so nothing else is loaded.
        if (! $this->plans->hasFeature($event->client, PlanFeature::MessageLog)) {
            return Inertia::render('client/notifications/index', ['event' => EventResource::make($event), 'locked' => true]);
        }

        // ?status= narrows the list to one status; anything else shows all.
        $status = NotificationStatus::tryFrom((string) $request->query('status'));

        return Inertia::render('client/notifications/index', [
            'event' => EventResource::make($event),
            'notifications' => NotificationResource::collection($this->notifications->forEvent($event, $status)),
            'statusCounts' => $this->notifications->statusCountsForEvent($event),
            'status' => $status?->value,
        ]);
    }

    public function show(Notification $notification): Response
    {
        $this->authorize('view', $notification);
        // The event header's poster shows the invitation's image.
        $notification->event->loadMissing('templateVersion.template.latestVersion');

        return Inertia::render('client/notifications/show', [
            'event' => EventResource::make($notification->event),
            'notification' => NotificationResource::make($notification->load(['guest', 'deliveries'])),
        ]);
    }
}
