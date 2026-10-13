<?php

namespace App\Domains\Billing\Models;

use App\Domains\Client\Models\Client;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A card a client saved with PayHere (preapproval). We only keep PayHere's
 * customer token (encrypted, never serialized) and the masked card; the card
 * number itself never reaches us. Removing a card wipes the token.
 *
 * @property string $id
 * @property string $client_id
 * @property string|null $customer_token PayHere's token to charge the card
 * @property string|null $card_holder_name
 * @property string|null $brand VISA, MASTER, AMEX…
 * @property string|null $last4
 * @property int|null $expiry_month
 * @property int|null $expiry_year
 * @property bool $is_default
 * @property string|null $added_by client user
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Client $client
 */
#[UseFactory(PaymentMethodFactory::class)]
#[Fillable([
    'client_id', 'customer_token', 'card_holder_name', 'brand', 'last4', 'expiry_month', 'expiry_year', 'is_default', 'added_by',
])]
#[Hidden(['customer_token'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = [
        'is_default' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'customer_token' => 'encrypted',
            'is_default' => 'boolean',
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
        ];
    }

    /** The card's expiry month is over. */
    public function isExpired(): bool
    {
        if ($this->expiry_month === null || $this->expiry_year === null) {
            return false;
        }

        return CarbonImmutable::create($this->expiry_year, $this->expiry_month)->endOfMonth()->isPast();
    }

    /** It can still be charged. */
    public function isChargeable(): bool
    {
        return $this->customer_token !== null && ! $this->trashed() && ! $this->isExpired();
    }

    /** "Visa •••• 4242" */
    public function label(): string
    {
        $brand = $this->brand ? ucfirst(strtolower($this->brand)) : 'Card';

        return $this->last4 ? "{$brand} •••• {$this->last4}" : $brand;
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
