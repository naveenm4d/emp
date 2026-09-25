<?php

namespace App\Domains\Notification\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of provider responses / webhook callbacks for a notification.
 *
 * @property string $id
 * @property string $notification_id
 * @property string|null $provider_id
 * @property string $status provider's own status string
 * @property array<string, mixed>|null $raw_payload
 * @property CarbonImmutable $created_at
 */
#[Fillable(['notification_id', 'provider_id', 'status', 'raw_payload'])]
class MessageDelivery extends Model
{
    use HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['raw_payload' => 'array'];
    }

    /** @return BelongsTo<Notification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
