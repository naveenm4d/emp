<?php

namespace App\Domains\Billing\Policies;

use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Models\ClientUser;

/** Saved cards of the account: the owner, and members allowed to manage billing. */
class PaymentMethodPolicy
{
    public function use(ClientUser $user, PaymentMethod $card): bool
    {
        return $card->client_id === $user->client_id && $user->hasPermission(ClientPermission::BillingManage);
    }

    public function update(ClientUser $user, PaymentMethod $card): bool
    {
        return $this->use($user, $card);
    }

    public function delete(ClientUser $user, PaymentMethod $card): bool
    {
        return $this->use($user, $card);
    }
}
