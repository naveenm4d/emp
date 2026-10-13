<?php

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A payment PayHere processed: paid, or declined. It is recorded from the
 * checkout once PayHere answers (abandoned or canceled checkouts never become
 * payments). The amount is the server's price, which PayHere reported
 * exactly. What was bought is applied once (fulfilled_at).
 *
 * @property string $id
 * @property string $client_id
 * @property string $order_id our reference at PayHere
 * @property PaymentPurpose $purpose
 * @property PaymentChannel $channel
 * @property PaymentStatus $status
 * @property string $description shown to the payer and on receipts
 * @property string|null $subject_type event or template
 * @property string|null $subject_id
 * @property string|null $subscription_id
 * @property CarbonImmutable|null $renewal_period_end renewals: the period end being renewed (one charge each)
 * @property array{interval?: string, quantity?: int, blocks?: int, guests?: int}|null $details
 * @property int $amount minor units
 * @property string $currency
 * @property string|null $payment_method_id
 * @property string|null $initiated_by client user; null = automatic (renewals)
 * @property string|null $payhere_payment_id
 * @property string|null $status_message
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $fulfilled_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 * @property-read Model|null $subject
 * @property-read Subscription|null $subscription
 * @property-read PaymentMethod|null $paymentMethod
 * @property-read ClientUser|null $initiator
 */
#[UseFactory(PaymentFactory::class)]
#[Fillable([
    'client_id', 'order_id', 'purpose', 'channel', 'status', 'description', 'subject_type', 'subject_id',
    'subscription_id', 'renewal_period_end', 'details', 'amount', 'currency', 'payment_method_id', 'initiated_by',
    'payhere_payment_id', 'status_message', 'paid_at', 'fulfilled_at', 'refunded_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'channel' => PaymentChannel::class,
            'status' => PaymentStatus::class,
            'details' => 'array',
            'amount' => 'integer',
            'renewal_period_end' => 'datetime',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /** The amount as PayHere expects it: "1490.00". */
    public function formattedAmount(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class)->withTrashed();
    }

    /** @return BelongsTo<ClientUser, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'initiated_by');
    }
}
