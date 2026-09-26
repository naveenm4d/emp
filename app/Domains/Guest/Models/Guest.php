<?php

namespace App\Domains\Guest\Models;

use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventLink;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\CheckInStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Rsvp\Models\Rsvp;
use Carbon\CarbonImmutable;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An attendee of an event. Guests have no account.
 *
 * @property string $id
 * @property string $event_id
 * @property GuestSource $source
 * @property ApprovalStatus $approval_status
 * @property GuestRsvpStatus $rsvp_status
 * @property CheckInStatus $check_in_status
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property string|null $invitation_message replaces the event's invitation message for this guest
 * @property string|null $reminder_message replaces the event's reminder message for this guest
 * @property CarbonImmutable|null $approval_status_changed_at
 * @property-read EventLink|null $link
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(GuestFactory::class)]
#[Fillable([
    'event_id', 'source', 'approval_status', 'rsvp_status', 'check_in_status',
    'name', 'email', 'phone', 'notes', 'invitation_message', 'reminder_message',
    'approval_status_changed_at',
])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'rsvp_status' => 'not_sent',
        'check_in_status' => 'not_checked_in',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source' => GuestSource::class,
            'approval_status' => ApprovalStatus::class,
            'rsvp_status' => GuestRsvpStatus::class,
            'check_in_status' => CheckInStatus::class,
            'approval_status_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<Rsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    /**
     * The guest's personal RSVP link (domain/{slug}/{code}).
     *
     * @return HasOne<EventLink, $this>
     */
    public function link(): HasOne
    {
        return $this->hasOne(EventLink::class);
    }

    /** @return HasOne<Rsvp, $this> */
    public function latestRsvp(): HasOne
    {
        // latestOfMany() tie-breaks with MAX(id), which Postgres lacks for
        // uuid. An ordered hasOne works because eager loading keeps the
        // first match per guest.
        return $this->hasOne(Rsvp::class)->latest();
    }
}
