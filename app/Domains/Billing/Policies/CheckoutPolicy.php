<?php

namespace App\Domains\Billing\Policies;

use App\Domains\Billing\Models\Checkout;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;

/** A checkout of the account, for people who manage billing. */
class CheckoutPolicy
{
    public function view(ClientUser $user, Checkout $checkout): bool
    {
        return $checkout->client_id === $user->client_id && $user->hasPermission(ClientPermission::BillingManage);
    }
}
