<?php

namespace App\Domains\Billing\Listeners;

use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Client\Events\ClientPlanChanged;

/** Staff changed the plan by hand: the subscription follows (or ends) so nobody is charged for the old plan. */
class FollowStaffPlanChange
{
    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptions,
    ) {}

    public function handle(ClientPlanChanged $event): void
    {
        $this->subscriptions->followPlanChange($event->client);
    }
}
