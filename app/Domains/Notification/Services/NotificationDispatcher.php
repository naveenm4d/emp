<?php

namespace App\Domains\Notification\Services;

use App\Domains\Notification\Contracts\NotificationDispatcherInterface;
use App\Domains\Notification\Contracts\NotificationRepositoryInterface;
use App\Domains\Notification\Enums\NotificationChannel;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Jobs\SendNotificationJob;
use App\Domains\Notification\Models\Notification;

class NotificationDispatcher implements NotificationDispatcherInterface
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function whatsapp(string $eventId, ?string $guestId, string $phone, string $message, ?string $rsvpId = null, ?NotificationKind $kind = null): Notification
    {
        return $this->queue(NotificationChannel::WhatsApp, $eventId, $guestId, $phone, $message, $rsvpId, $kind);
    }

    private function queue(
        NotificationChannel $channel,
        string $eventId,
        ?string $guestId,
        string $recipient,
        string $message,
        ?string $rsvpId,
        ?NotificationKind $kind,
    ): Notification {
        /** @var Notification $notification */
        $notification = $this->notifications->create([
            'event_id' => $eventId,
            'guest_id' => $guestId,
            'rsvp_id' => $rsvpId,
            'channel' => $channel,
            'kind' => $kind,
            'status' => NotificationStatus::Pending,
            'recipient' => $recipient,
            'message' => $message,
        ]);

        // Dispatched after commit so the worker never sees an uncommitted row.
        SendNotificationJob::dispatch($notification->id);

        return $notification;
    }
}
