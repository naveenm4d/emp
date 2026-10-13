<?php

namespace App\Domains\Billing\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Every call PayHere (or anyone) made to the notify URL, kept for audits and
 * disputes. The card token is stripped before it is stored.
 *
 * @property int $id
 * @property string|null $order_id
 * @property array<string, mixed> $payload
 * @property bool $signature_valid
 * @property string|null $error why it was rejected or not applied
 * @property CarbonImmutable $created_at
 */
#[Fillable(['order_id', 'payload', 'signature_valid', 'error'])]
class PaymentNotification extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
        ];
    }
}
