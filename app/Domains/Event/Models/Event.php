<?php

namespace App\Domains\Event\Models;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Models\Rsvp;
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
 * @property bool $auto_reminders send reminders automatically (RsvpReminderCommand)
 * @property int $remind_after_days automatic reminder this many days after sending, if no reply
 * @property int $remind_before_days automatic reminder this many days before the event date
 * @property array{texts?: array<string, string>, colors?: array<string, string>, sections?: array<string, bool>}|null $customizations the client's changes to the template's editable texts, colours and sections
 * @property string|null $rendered_path generated invitation HTML on the render disk
 * @property string|null $rendered_hash
 * @property CarbonImmutable|null $rendered_at
 * @property-read TemplateVersion $templateVersion
 * @property-read Collection<int, EventMedia> $media
 * @property-read EventLink|null $publicLink
 * @property CarbonImmutable|null $deleted_at events are only soft-deleted
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(EventFactory::class)]
#[Fillable([
    'client_id', 'template_version_id', 'title', 'slug', 'description', 'state', 'max_capacity',
    'registration_type', 'registration_open', 'event_type', 'location_name',
    'location_address', 'map_url', 'event_date', 'start_time', 'end_time', 'customizations',
    'invitation_message', 'reminder_message', 'auto_reminders', 'remind_after_days', 'remind_before_days',
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
