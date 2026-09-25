<?php

namespace App\Domains\Guest\Models;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Enums\CheckInStatus;
use App\Domains\Guest\Enums\GuestSource;
use App\Domains\Guest\Enums\RsvpStatus;
use App\Domains\Invitation\Models\Invitation;
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
 * @property RsvpStatus $rsvp_status
 * @property CheckInStatus $check_in_status
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $notes
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(GuestFactory::class)]
#[Fillable([
    'event_id', 'source', 'approval_status', 'rsvp_status', 'check_in_status',
    'name', 'email', 'phone', 'notes',
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
            'rsvp_status' => RsvpStatus::class,
            'check_in_status' => CheckInStatus::class,
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** @return HasOne<Invitation, $this> */
    public function latestInvitation(): HasOne
    {
        // latestOfMany() tie-breaks with MAX(id), which Postgres lacks for
        // uuid. An ordered hasOne works because eager loading keeps the
        // first match per guest.
        return $this->hasOne(Invitation::class)->latest();
    }
}
