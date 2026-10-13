<?php

namespace App\Domains\Billing\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Billing\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<Subscription>
 */
interface SubscriptionRepositoryInterface extends RepositoryInterface
{
    public function forClient(string $clientId): ?Subscription;

    /** Loads the subscription with a row lock. Must be called inside a transaction. */
    public function findAndLock(string $id): Subscription;

    /** @return Collection<int, Subscription> renewing subscriptions whose period ends before $until */
    public function dueForRenewal(CarbonImmutable $until): Collection;

    /** @return Collection<int, Subscription> canceled subscriptions whose period is over */
    public function endedWithoutRenewal(CarbonImmutable $now): Collection;

    /** @return Collection<int, Subscription> renewing yearly subscriptions ending before $until that weren't reminded */
    public function dueForRenewalReminder(CarbonImmutable $until): Collection;
}
