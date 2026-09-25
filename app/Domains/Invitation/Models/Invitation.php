<?php

namespace App\Domains\Invitation\Models;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Enums\InvitationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $event_id
 * @property string $guest_id
 * @property InvitationStatus $status
 * @property string $token 64 hex chars, used in the public RSVP link
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(InvitationFactory::class)]
#[Fillable(['event_id', 'guest_id', 'status', 'token', 'sent_at', 'expires_at', 'responded_at'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->status === InvitationStatus::Expired
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /** Absolute link to the guest's RSVP page on the site. */
    public function rsvpUrl(): string
    {
        return route('site.rsvp.show', $this->token);
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
}
