<?php

namespace App\Domains\Notification\Services;

use App\Core\Services\BaseService;
use App\Domains\Notification\Channels\ChannelRegistry;
use App\Domains\Notification\Contracts\NotificationRepositoryInterface;
use App\Domains\Notification\Contracts\NotificationServiceInterface;
use App\Domains\Notification\DTOs\ProviderStatusUpdate;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Exceptions\ChannelDeliveryException;
use App\Domains\Notification\Exceptions\NotificationNotRetryableException;
use App\Domains\Notification\Jobs\SendNotificationJob;
use App\Domains\Notification\Models\Notification;

class NotificationService extends BaseService implements NotificationServiceInterface
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
        private readonly ChannelRegistry $channels,
    ) {}

    public function deliver(string $notificationId): void
    {
        $notification = $this->notifications->find($notificationId);

        // Deleted, already sent, or already failed: nothing to do.
        if (! $notification || $notification->status !== NotificationStatus::Pending) {
            return;
        }

        $this->notifications->update($notification, ['attempts' => $notification->attempts + 1]);

        try {
            $result = $this->channels->for($notification->channel)->send($notification->recipient, $notification->message);
        } catch (ChannelDeliveryException $e) {
            $this->notifications->update($notification, ['error' => $e->getMessage()]);
            $this->notifications->recordDelivery($notification, [
                'status' => 'error',
                'raw_payload' => ['error' => $e->getMessage(), 'attempt' => $notification->attempts],
            ]);

            throw $e; // let the queue retry with backoff
        }

        $this->transaction(function () use ($notification, $result) {
            $this->notifications->update($notification, [
                'status' => NotificationStatus::Sent,
                'provider_message_id' => $result->messageId,
                'sent_at' => now(),
                'error' => null,
            ]);

            $this->notifications->recordDelivery($notification, [
                'provider_id' => $result->messageId,
                'status' => 'accepted',
                'raw_payload' => $result->payload,
            ]);
        });
    }

    public function markFailed(string $notificationId, string $error): void
    {
        $notification = $this->notifications->find($notificationId);

        if ($notification && $notification->status === NotificationStatus::Pending) {
            $this->notifications->update($notification, [
                'status' => NotificationStatus::Failed,
                'error' => $error,
            ]);
        }
    }

    public function retry(Notification $notification): Notification
    {
        if ($notification->status !== NotificationStatus::Failed) {
            throw new NotificationNotRetryableException;
        }

        /** @var Notification $notification */
        $notification = $this->notifications->update($notification, [
            'status' => NotificationStatus::Pending,
            'error' => null,
        ]);

        SendNotificationJob::dispatch($notification->id);

        return $notification;
    }

    public function applyStatusUpdate(ProviderStatusUpdate $update): void
    {
        $notification = $this->notifications->findByProviderMessageId($update->messageId);

        if (! $notification) {
            return;
        }

        $this->transaction(function () use ($notification, $update) {
            // Meta may send callbacks out of order: a late "delivered" never downgrades "read".
            $attributes = match ($update->status) {
                'delivered' => $notification->delivered_at || $notification->status === NotificationStatus::Read ? [] : [
                    'status' => NotificationStatus::Delivered,
                    'delivered_at' => $update->occurredAt ?? now(),
                ],
                'read' => $notification->status === NotificationStatus::Read ? [] : [
                    'status' => NotificationStatus::Read,
                    'delivered_at' => $notification->delivered_at ?? $update->occurredAt ?? now(),
                    'read_at' => $update->occurredAt ?? now(),
                ],
                'failed' => [
                    'status' => NotificationStatus::Failed,
                    'error' => $update->error ?? 'Provider reported delivery failure.',
                ],
                default => [],
            };

            if ($attributes !== []) {
                $this->notifications->update($notification, $attributes);
            }

            $this->notifications->recordDelivery($notification, [
                'provider_id' => $update->messageId,
                'status' => $update->status,
                'raw_payload' => $update->payload,
            ]);
        });
    }

    public function requeueStalePending(int $olderThanMinutes): int
    {
        $ids = $this->notifications->stalePendingIds($olderThanMinutes);

        $ids->each(fn (string $id) => SendNotificationJob::dispatch($id));

        return $ids->count();
    }
}
