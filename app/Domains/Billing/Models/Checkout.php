<?php

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\PaymentChannel;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use Carbon\CarbonImmutable;
use Database\Factories\CheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A purchase sent to PayHere and not processed yet. It is not billing
 * history: once PayHere answers (paid or declined) it becomes a Payment and
 * is deleted; one nobody finished is pruned after a day. The amount is the
 * server's price, which PayHere must report back exactly.
 *
 * @property string $id
 * @property string $client_id
 * @property string $order_id our reference at PayHere
 * @property PaymentPurpose $purpose
 * @property PaymentChannel $channel
 * @property string $description
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $subscription_id renewals
 * @property CarbonImmutable|null $renewal_period_end renewals: the period end being renewed
 * @property array<string, mixed>|null $details
 * @property int $amount minor units
 * @property string $currency
 * @property string|null $payment_method_id the card charged (saved card, or saved by preapproval)
 * @property string|null $initiated_by client user; null = automatic (renewals)
 * @property string|null $payhere_payment_id PayHere's reference while it is still pending
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 * @property-read Model|null $subject
 * @property-read PaymentMethod|null $paymentMethod
 * @property-read ClientUser|null $initiator
 */
#[UseFactory(CheckoutFactory::class)]
#[Fillable([
    'client_id', 'order_id', 'purpose', 'channel', 'description', 'subject_type', 'subject_id', 'subscription_id',
    'renewal_period_end', 'details', 'amount', 'currency', 'payment_method_id', 'initiated_by', 'payhere_payment_id',
])]
class Checkout extends Model
{
    /** @use HasFactory<CheckoutFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'channel' => PaymentChannel::class,
            'details' => 'array',
            'amount' => 'integer',
            'renewal_period_end' => 'datetime',
        ];
    }

    /** A new order reference: unique, unguessable and short enough for PayHere. */
    public static function newOrderId(): string
    {
        return 'EMP-'.Str::upper((string) Str::ulid());
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
