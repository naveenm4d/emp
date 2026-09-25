<?php

namespace App\Domains\Notification\Console\Commands;

use App\Domains\Notification\Contracts\NotificationServiceInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:requeue-pending {--minutes=10 : Only notifications pending longer than this}')]
#[Description('Re-queue notifications stuck in pending (e.g. after a worker outage)')]
class RequeuePendingNotificationsCommand extends Command
{
    public function handle(NotificationServiceInterface $notifications): int
    {
        $count = $notifications->requeueStalePending((int) $this->option('minutes'));

        $this->components->info("Re-queued {$count} pending notification(s).");

        return self::SUCCESS;
    }
}
