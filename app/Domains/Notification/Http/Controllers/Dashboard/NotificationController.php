<?php

namespace App\Domains\Notification\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Models\Event;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Http\Resources\NotificationResource;
use App\Domains\Notification\Models\Notification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends InertiaController
{
    public function __construct(
        private readonly NotificationQueryServiceInterface $notifications,
        private readonly ClientPlanServiceInterface $plans,
    ) {}

    public function index(Event $event): Response
    {
        $this->authorize('view', $event);

        // Not in the plan: the tab shows an upgrade prompt, so nothing else is loaded.
        if (! $this->plans->hasFeature($event->client, PlanFeature::MessageLog)) {
            return Inertia::render('client/notifications/index', ['event' => EventResource::make($event), 'locked' => true]);
        }

        return Inertia::render('client/notifications/index', [
            'event' => EventResource::make($event),
            'notifications' => NotificationResource::collection($this->notifications->forEvent($event)),
        ]);
    }

    public function show(Notification $notification): Response
    {
        $this->authorize('view', $notification);

        return Inertia::render('client/notifications/show', [
            'event' => EventResource::make($notification->event),
            'notification' => NotificationResource::make($notification->load(['guest', 'deliveries'])),
        ]);
    }
}
