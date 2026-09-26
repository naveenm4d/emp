<?php

namespace App\Domains\Rsvp\Models;

use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Notification\Models\Notification;
use App\Domains\Rsvp\Enums\RsvpStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RsvpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $event_id
 * @property string $guest_id
 * @property RsvpStatus $status
 * @property string $token 64 hex chars, used in the public RSVP link
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $responded_at
 * @property int $reminder_count reminders sent for this link (manual and automatic)
 * @property CarbonImmutable|null $last_reminded_at
 * @property CarbonImmutable|null $auto_after_reminded_at when the automatic "days after sending" reminder went out
 * @property CarbonImmutable|null $auto_before_reminded_at when the automatic "days before the event" reminder went out
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(RsvpFactory::class)]
#[Fillable([
    'event_id', 'guest_id', 'status', 'token', 'sent_at', 'expires_at', 'responded_at',
    'reminder_count', 'last_reminded_at', 'auto_after_reminded_at', 'auto_before_reminded_at',
])]
class Rsvp extends Model
{
    /** @use HasFactory<RsvpFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'pending',
        'reminder_count' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RsvpStatus::class,
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'reminder_count' => 'integer',
            'last_reminded_at' => 'datetime',
            'auto_after_reminded_at' => 'datetime',
            'auto_before_reminded_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->status === RsvpStatus::Expired
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * The guest's personal link (domain/{slug}/{code}); it always opens the
     * guest's latest RSVP link.
     */
    public function rsvpUrl(): string
    {
        $this->loadMissing('guest.link.event');

        return $this->guest->link?->url()
            ?? app(EventLinkServiceInterface::class)->createForGuest($this->guest)->url();
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

    /**
     * The most recent message sent for this link (a resend adds a new one).
     *
     * @return HasOne<Notification, $this>
     */
    public function latestNotification(): HasOne
    {
        // Ordered hasOne rather than latestOfMany(): see Guest::latestRsvp().
        return $this->hasOne(Notification::class)->latest();
    }
}
