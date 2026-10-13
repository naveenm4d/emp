<?php

namespace App\Domains\Billing\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<Subscription>
 */
class SubscriptionRepository extends BaseRepository implements SubscriptionRepositoryInterface
{
    protected function model(): string
    {
        return Subscription::class;
    }

    public function forClient(string $clientId): ?Subscription
    {
        return $this->query()->where('client_id', $clientId)->first();
    }

    public function findAndLock(string $id): Subscription
    {
        /** @var Subscription */
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function dueForRenewal(CarbonImmutable $until): Collection
    {
        return $this->query()
            ->where('status', SubscriptionStatus::Active)
            ->where('auto_renew', true)
            ->where('current_period_end', '<=', $until)
            ->orderBy('current_period_end')
            ->get();
    }

    public function endedWithoutRenewal(CarbonImmutable $now): Collection
    {
        return $this->query()
            ->where('status', SubscriptionStatus::Canceled)
            ->where('current_period_end', '<=', $now)
            ->get();
    }

    public function dueForRenewalReminder(CarbonImmutable $until): Collection
    {
        return $this->query()
            ->with('client.owner')
            ->where('status', SubscriptionStatus::Active)
            ->where('auto_renew', true)
            ->where(fn ($query) => $query
                ->where('interval', BillingInterval::Yearly)
                ->orWhere('next_interval', BillingInterval::Yearly))
            ->whereNull('renewal_reminded_at')
            ->where('current_period_end', '<=', $until)
            ->get();
    }
}
