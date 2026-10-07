<?php

namespace App\Domains\Event\Models;

use App\Domains\Client\Models\Client;
use App\Domains\Event\DTOs\RegistrationSettings;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Models\Rsvp;
use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Models\VenueElement;
use App\Domains\Template\Models\TemplateVersion;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

/**
 * @property string $id
 * @property string $client_id
 * @property string $template_version_id the template version this event is designed with (pinned)
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property EventState $state
 * @property int $max_capacity 0 = unlimited
 * @property RegistrationType $registration_type who can join: open, approval_required or guest_list_only
 * @property bool $registration_open
 * @property EventType|null $event_type
 * @property string|null $location_name
 * @property string|null $location_address
 * @property string|null $map_url
 * @property CarbonImmutable|null $event_date
 * @property string|null $start_time HH:MM:SS
 * @property string|null $end_time HH:MM:SS
 * @property string|null $invitation_message default WhatsApp text sent with RSVP links (placeholders: see RsvpMessage)
 * @property string|null $reminder_message default WhatsApp text for RSVP reminders
 * @property string|null $invitation_subject email subject of the invitation (email only, never sent on WhatsApp or SMS; stored for now)
 * @property string|null $reminder_subject email subject of the reminder (email only)
 * @property bool $auto_reminders send reminders automatically (RsvpReminderCommand)
 * @property int $remind_after_days automatic reminder this many days after sending, if no reply
 * @property int $remind_before_days automatic reminder this many days before the event date
 * @property array{texts?: array<string, string>, colors?: array<string, string>, sections?: array<string, bool>}|null $customizations the client's changes to the template's editable texts, colours and sections
 * @property array<string, mixed>|null $event_registration_settings what guests are asked when registering / RSVPing; read through registrationSettings()
 * @property int $extra_guests guests bought on top of the plan's limit (in blocks of config emp.extra_guests_block)
 * @property int|null $max_invitations_per_guest staff override of the invitations each guest can get (null = platform default)
 * @property int|null $max_reminders_per_guest staff override of the reminders each guest can get (null = platform default)
 * @property CarbonImmutable|null $responses_lock_at when guests can no longer change their answer (if changes are allowed)
 * @property string|null $rendered_path generated invitation HTML on the render disk
 * @property string|null $rendered_hash
 * @property CarbonImmutable|null $rendered_at
 * @property-read TemplateVersion $templateVersion
 * @property-read Collection<int, EventMedia> $media
 * @property-read EventLink|null $publicLink
 * @property-read Collection<int, RegistrationQuestion> $registrationQuestions
 * @property CarbonImmutable|null $deleted_at events are only soft-deleted
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(EventFactory::class)]
#[Fillable([
    'client_id', 'template_version_id', 'title', 'slug', 'description', 'state', 'max_capacity',
    'registration_type', 'registration_open', 'event_type', 'location_name',
    'location_address', 'map_url', 'event_date', 'start_time', 'end_time', 'customizations',
    'invitation_message', 'reminder_message', 'invitation_subject', 'reminder_subject', 'auto_reminders', 'remind_after_days', 'remind_before_days',
    'event_registration_settings', 'responses_lock_at', 'max_invitations_per_guest', 'max_reminders_per_guest', 'extra_guests',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * First URL segments taken by the app, so they can't be event slugs
     * (event links are domain/{slug}/{code}).
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = ['app', 'admin', 'webhooks', 'preview', 'rsvp', 'e', 'storage', 'build', 'up', 'api'];

    protected $attributes = [
        'state' => 'draft',
        'max_capacity' => 0,
        'registration_type' => 'guest_list_only',
        'registration_open' => false,
        'extra_guests' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => EventState::class,
            'event_type' => EventType::class,
            'customizations' => 'array',
            'max_capacity' => 'integer',
            'registration_type' => RegistrationType::class,
            'auto_reminders' => 'boolean',
            'remind_after_days' => 'integer',
            'remind_before_days' => 'integer',
            'registration_open' => 'boolean',
            'event_date' => 'date:Y-m-d',
            'rendered_at' => 'datetime',
            'event_registration_settings' => 'array',
            'responses_lock_at' => 'datetime',
            'extra_guests' => 'integer',
            'max_invitations_per_guest' => 'integer',
            'max_reminders_per_guest' => 'integer',
        ];
    }

    public function hasUnlimitedCapacity(): bool
    {
        return $this->max_capacity === 0;
    }

    public function isOwnedBy(Client $client): bool
    {
        return $this->client_id === $client->id;
    }

    /** Public registrations wait for the client's approval. */
    public function requiresApproval(): bool
    {
        return $this->registration_type->requiresApproval();
    }

    public function acceptsPublicRegistrations(): bool
    {
        return $this->state === EventState::Published
            && $this->registration_type->hasPublicRegistration()
            && $this->registration_open;
    }

    /** What guests are asked when they register or RSVP; unsaved events use the defaults for their type. */
    public function registrationSettings(): RegistrationSettings
    {
        $defaults = RegistrationSettings::defaultsFor($this->event_type, $this->registration_type);

        return $this->event_registration_settings === null
            ? $defaults
            : RegistrationSettings::fromArray($this->event_registration_settings, $defaults);
    }

    /** When the event starts: its date plus start time (start of day without one). */
    public function startsAt(): ?CarbonImmutable
    {
        if ($this->event_date === null) {
            return null;
        }

        return $this->start_time === null
            ? $this->event_date->startOfDay()
            : $this->event_date->setTimeFromTimeString($this->start_time);
    }

    /**
     * Guests may change an answer they already gave: the client allows it, the
     * lock time (if set) has not passed and the event has not started.
     */
    public function allowsResponseChanges(): bool
    {
        if (! $this->registrationSettings()->responsesEditable) {
            return false;
        }

        $now = now();

        return ($this->responses_lock_at === null || $now->lessThan($this->responses_lock_at))
            && ($this->startsAt() === null || $now->lessThan($this->startsAt()));
    }

    /**
     * Most invitations (send, resend, re-invite) one guest can get; failed
     * messages don't count. Staff override, else the client's plan, else the
     * platform default.
     */
    public function invitationLimit(): int
    {
        return $this->max_invitations_per_guest
            ?? $this->client->plan->messageLimits()['invitations']
            ?? (int) config('emp.max_invitations_per_guest');
    }

    /** Most reminders (manual and automatic) one guest can get; like invitationLimit(). */
    public function reminderLimit(): int
    {
        return $this->max_reminders_per_guest
            ?? $this->client->plan->messageLimits()['reminders']
            ?? (int) config('emp.max_reminders_per_guest');
    }

    /** Most guests the event can have: the plan's limit plus extra guests bought; null = unlimited. */
    public function guestLimit(): ?int
    {
        $limit = $this->client->plan->maxGuestsPerEvent();

        return $limit === null ? null : $limit + $this->extra_guests;
    }

    /** Temporary signed link to the client's preview of the invitation. */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute(
            'web.preview',
            now()->addMinutes((int) config('emp.preview_link_minutes')),
            ['event' => $this->id],
        );
    }

    /** Storage segment grouping this event's files by its template's category: "{category}/{event_id}". */
    public function storageDirectory(): string
    {
        $this->loadMissing('templateVersion.template');

        return "{$this->templateVersion->template->category->value}/{$this->id}";
    }

    /** @return BelongsTo<TemplateVersion, $this> */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /** @return HasMany<EventMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(EventMedia::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Guest, $this> */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /** @return HasMany<Rsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    /** @return HasMany<RegistrationQuestion, $this> */
    public function registrationQuestions(): HasMany
    {
        return $this->hasMany(RegistrationQuestion::class)->orderBy('sort_order');
    }

    /**
     * Seating tables, in the order the client arranged them.
     *
     * @return HasMany<EventTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(EventTable::class)->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * The stage, poruwa, buffet, … on the seating floor plan.
     *
     * @return HasMany<VenueElement, $this>
     */
    public function venueElements(): HasMany
    {
        return $this->hasMany(VenueElement::class);
    }

    /** @return HasMany<EventLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(EventLink::class);
    }

    /**
     * The event's public URL (the link without a guest).
     *
     * @return HasOne<EventLink, $this>
     */
    public function publicLink(): HasOne
    {
        return $this->hasOne(EventLink::class)->whereNull('guest_id');
    }
}
