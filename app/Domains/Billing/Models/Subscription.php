<?php

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Models\Client;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's Business subscription (one per client, reused when they
 * subscribe again). The client's plan_expires_at follows current_period_end.
 *
 * @property string $id
 * @property string $client_id
 * @property ClientPlan $plan
 * @property BillingInterval $interval
 * @property BillingInterval|null $next_interval a switch that applies at the next renewal
 * @property SubscriptionStatus $status
 * @property bool $auto_renew
 * @property string|null $payment_method_id the card renewals are charged to
 * @property CarbonImmutable $current_period_start
 * @property CarbonImmutable $current_period_end
 * @property CarbonImmutable|null $canceled_at
 * @property CarbonImmutable|null $renewal_reminded_at the upcoming-renewal email for this period went out
 * @property string|null $ended_reason renewal_failed, canceled, plan_changed
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 * @property-read PaymentMethod|null $paymentMethod
 */
#[UseFactory(SubscriptionFactory::class)]
#[Fillable([
    'client_id', 'plan', 'interval', 'next_interval', 'status', 'auto_renew', 'payment_method_id',
    'current_period_start', 'current_period_end', 'canceled_at', 'renewal_reminded_at', 'ended_reason',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan' => ClientPlan::class,
            'interval' => BillingInterval::class,
            'next_interval' => BillingInterval::class,
            'status' => SubscriptionStatus::class,
            'auto_renew' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'renewal_reminded_at' => 'datetime',
        ];
    }

    /** Still running (renewing or not). */
    public function isRunning(): bool
    {
        return $this->status !== SubscriptionStatus::Expired && $this->current_period_end->isFuture();
    }

    /** The interval the next period is charged at. */
    public function renewalInterval(): BillingInterval
    {
        return $this->next_interval ?? $this->interval;
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }
}
