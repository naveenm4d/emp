<?php

namespace App\Domains\Notification\Contracts;

use App\Domains\Notification\DTOs\ProviderStatusUpdate;
use App\Domains\Notification\Models\Notification;

interface NotificationServiceInterface
{
    /** Hands a pending notification to its channel provider (called by the queue). */
    public function deliver(string $notificationId): void;

    public function markFailed(string $notificationId, string $error): void;

    /** Staff action: re-queue a failed notification. */
    public function retry(Notification $notification): Notification;

    /** Applies a provider callback. Unknown message ids are ignored. */
    public function applyStatusUpdate(ProviderStatusUpdate $update): void;

    /** Re-queues notifications stuck in pending. Returns how many. */
    public function requeueStalePending(int $olderThanMinutes): int;
}
