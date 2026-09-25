<?php

namespace App\Domains\Notification\Models;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Enums\NotificationChannel;
use App\Domains\Notification\Enums\NotificationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An outbound message to a guest (WhatsApp / email / SMS).
 *
 * @property string $id
 * @property string $event_id
 * @property string|null $guest_id
 * @property NotificationChannel $channel
 * @property NotificationStatus $status
 * @property string $recipient
 * @property string $message
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property int $attempts
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(NotificationFactory::class)]
#[Fillable([
    'event_id', 'guest_id', 'channel', 'status', 'recipient', 'message',
    'provider_message_id', 'error', 'attempts', 'sent_at', 'delivered_at',
])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Guest, $this> */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /** @return HasMany<MessageDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class)->latest();
    }
}
