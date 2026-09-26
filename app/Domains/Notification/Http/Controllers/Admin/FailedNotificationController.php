<?php

namespace App\Domains\Notification\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Contracts\NotificationServiceInterface;
use App\Domains\Notification\Http\Resources\NotificationResource;
use App\Domains\Notification\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FailedNotificationController extends InertiaController
{
    public function index(NotificationQueryServiceInterface $notifications): Response
    {
        return Inertia::render('admin/notifications/failed', [
            'notifications' => NotificationResource::collection($notifications->failed()),
        ]);
    }

    public function retry(Notification $notification, NotificationServiceInterface $notifications): RedirectResponse
    {
        $notifications->retry($notification);

        return $this->backWithSuccess('Notification re-queued.');
    }
}
