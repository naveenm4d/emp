<?php

namespace App\Domains\Billing\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Billing\Models\Checkout;
use Carbon\CarbonImmutable;

/**
 * @extends RepositoryInterface<Checkout>
 */
interface CheckoutRepositoryInterface extends RepositoryInterface
{
    public function findByOrderId(string $orderId): ?Checkout;

    /** Loads the checkout with a row lock, or null when it was already processed. Must be called inside a transaction. */
    public function findAndLock(string $id): ?Checkout;

    /** The renewal attempt for a subscription's period, if one is waiting for PayHere. */
    public function renewalFor(string $subscriptionId, CarbonImmutable $periodEnd): ?Checkout;

    /** Deletes checkouts started before $before that PayHere never processed (renewals excepted); returns how many. */
    public function pruneAbandoned(CarbonImmutable $before): int;
}
