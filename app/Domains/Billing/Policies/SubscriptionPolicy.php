<?php

namespace App\Domains\Billing\Policies;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;

class SubscriptionPolicy
{
    public function update(ClientUser $user, Subscription $subscription): bool
    {
        return $subscription->client_id === $user->client_id && $user->hasPermission(ClientPermission::BillingManage);
    }
}
