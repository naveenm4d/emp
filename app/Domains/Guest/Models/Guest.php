<?php

namespace App\Domains\Guest\Models;

use App\Domains\Event\DTOs\RegistrationSettings;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\EventLink;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\CheckInStatus;
use App\Domains\Guest\Enums\GuestRsvpStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use App\Domains\Rsvp\Models\Rsvp;
use App\Domains\Seating\Models\SeatAssignment;
use Carbon\CarbonImmutable;
use Closure;
use Database\Factories\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string|null $address
 * @property string|null $company
 * @property string|null $job_title
 * @property int|null $invited_additional_guests plus-ones the client invited the guest with (null = the event's settings)
 * @property int|null $invited_children children the client invited the guest with (null = the event's settings)
 * @property int $additional_guests plus-ones coming with the guest
 * @property int $children children coming with the guest
 * @property list<string>|null $dietary_restrictions DietaryOption values
 * @property string|null $dietary_notes
 * @property-read Collection<int, RegistrationAnswer> $answers
 * @property-read int|null $invitations_sent with messagesSentCounts()
 * @property-read int|null $reminders_sent with messagesSentCounts()
 * @property-read EventLink|null $link
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(GuestFactory::class)]
#[Fillable([
    'event_id', 'source', 'approval_status', 'rsvp_status', 'check_in_status',
    'name', 'email', 'phone', 'notes', 'invitation_message', 'reminder_message',
    'approval_status_changed_at', 'address', 'company', 'job_title', 'additional_guests', 'children',
    'dietary_restrictions', 'dietary_notes', 'invited_additional_guests', 'invited_children',
])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'rsvp_status' => 'not_sent',
        'check_in_status' => 'not_checked_in',
        'additional_guests' => 0,
        'children' => 0,
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
            'additional_guests' => 'integer',
            'children' => 'integer',
            'invited_additional_guests' => 'integer',
            'invited_children' => 'integer',
            'dietary_restrictions' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** How many people the guest's answer covers: the guest, plus-ones and children. */
    public function partySize(): int
    {
        return 1 + $this->additional_guests + $this->children;
    }

    /**
     * How many plus-ones and children the guest may bring: what the client
     * invited them with, or else what the event's settings allow.
     *
     * @return array{additional: int, children: int}
     */
    public function partyAllowance(RegistrationSettings $settings): array
    {
        return [
            'additional' => $this->invited_additional_guests ?? ($settings->plusOnes ? $settings->maxAdditionalGuests : 0),
            'children' => $this->invited_children ?? ($settings->children ? RegistrationSettings::MAX_ADDITIONAL_GUESTS : 0),
        ];
    }

    /**
     * A person of the guest's party by position: 0 is the guest, then their
     * plus-ones, then their children ("John", "John's guest 2", "John's child").
     */
    public function partyMemberLabel(int $member): string
    {
        if ($member === 0) {
            return $this->name;
        }

        if ($member <= $this->additional_guests) {
            return $this->additional_guests === 1 ? "{$this->name}'s guest" : "{$this->name}'s guest {$member}";
        }

        $child = $member - $this->additional_guests;

        return $this->children === 1 ? "{$this->name}'s child" : "{$this->name}'s child {$child}";
    }

    /** The guest answered their RSVP (attending, not attending or maybe). */
    public function hasResponded(): bool
    {
        return in_array($this->rsvp_status, [GuestRsvpStatus::Confirmed, GuestRsvpStatus::Declined, GuestRsvpStatus::Maybe], true);
    }

    /**
     * Answers to the event's custom questions.
     *
     * @return HasMany<RegistrationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationAnswer::class);
    }

    /**
     * Where the guest's party sits (one row per person).
     *
     * @return HasMany<SeatAssignment, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(SeatAssignment::class)->orderBy('party_member');
    }

    /**
     * withCount() of the invitations and reminders sent to a guest, as
     * invitations_sent / reminders_sent; failed messages don't count (they
     * don't use up the event's per-guest limits).
     *
     * @return array<string, Closure(Builder<Notification>): void>
     */
    public static function messagesSentCounts(): array
    {
        $sent = fn (NotificationKind $kind) => function (Builder $query) use ($kind): void {
            $query->where('kind', $kind)->where('status', '!=', NotificationStatus::Failed);
        };

        return [
            'notifications as invitations_sent' => $sent(NotificationKind::RsvpInvitation),
            'notifications as reminders_sent' => $sent(NotificationKind::RsvpReminder),
        ];
    }

    /**
     * WhatsApp messages sent to the guest (invitations, reminders).
     *
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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
