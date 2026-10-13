<?php

namespace App\Domains\Billing\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Billing\Contracts\CheckoutRepositoryInterface;
use App\Domains\Billing\Models\Checkout;
use Carbon\CarbonImmutable;

/**
 * @extends BaseRepository<Checkout>
 */
class CheckoutRepository extends BaseRepository implements CheckoutRepositoryInterface
{
    protected function model(): string
    {
        return Checkout::class;
    }

    public function findByOrderId(string $orderId): ?Checkout
    {
        return $this->query()->where('order_id', $orderId)->first();
    }

    public function findAndLock(string $id): ?Checkout
    {
        return $this->query()->lockForUpdate()->find($id);
    }

    public function renewalFor(string $subscriptionId, CarbonImmutable $periodEnd): ?Checkout
    {
        return $this->query()
            ->where('subscription_id', $subscriptionId)
            ->where('renewal_period_end', $periodEnd)
            ->first();
    }

    public function pruneAbandoned(CarbonImmutable $before): int
    {
        // Renewals stay until settled: the renewal run asks PayHere about them.
        return $this->query()
            ->whereNull('subscription_id')
            ->where('created_at', '<', $before)
            ->delete();
    }
}
