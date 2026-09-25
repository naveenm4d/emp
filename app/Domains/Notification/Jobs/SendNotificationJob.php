<?php

namespace App\Domains\Notification\Jobs;

use App\Domains\Notification\Contracts\NotificationServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Delivers one notification through its channel provider, retrying with
 * backoff. After the final attempt the notification is marked failed and
 * can be retried manually by staff.
 */
class SendNotificationJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds between attempts */
    public array $backoff = [30, 120];

    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $notificationId,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->notificationId;
    }

    public function handle(NotificationServiceInterface $notifications): void
    {
        $notifications->deliver($this->notificationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(NotificationServiceInterface::class)->markFailed(
            $this->notificationId,
            $exception?->getMessage() ?? 'Delivery failed.',
        );
    }
}
