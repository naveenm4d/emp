<?php

namespace App\Domains\Billing\Policies;

use App\Domains\Billing\Models\Payment;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;

/** Buying and the payment history: the owner, and members allowed to manage billing. */
class PaymentPolicy
{
    public function viewAny(ClientUser $user): bool
    {
        return $user->hasPermission(ClientPermission::BillingManage);
    }

    public function create(ClientUser $user): bool
    {
        return $user->hasPermission(ClientPermission::BillingManage);
    }

    public function view(ClientUser $user, Payment $payment): bool
    {
        return $payment->client_id === $user->client_id && $user->hasPermission(ClientPermission::BillingManage);
    }
}
