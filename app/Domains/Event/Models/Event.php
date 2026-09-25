<?php

namespace App\Domains\Event\Models;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventState;
use App\Domains\Guest\Models\Guest;
use App\Domains\Invitation\Models\Invitation;
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
 * @property bool $require_approval
 * @property bool $registration_open
 * @property string|null $event_type
 * @property string|null $location_name
 * @property string|null $location_address
 * @property string|null $map_url
 * @property CarbonImmutable|null $event_date
 * @property string|null $start_time HH:MM:SS
 * @property string|null $end_time HH:MM:SS
 * @property string|null $rendered_path generated invitation HTML on the render disk
 * @property string|null $rendered_hash
 * @property CarbonImmutable|null $rendered_at
 * @property-read TemplateVersion $templateVersion
 * @property-read Collection<int, EventMedia> $media
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(EventFactory::class)]
#[Fillable([
    'client_id', 'template_version_id', 'title', 'slug', 'description', 'state', 'max_capacity',
    'require_approval', 'registration_open', 'event_type', 'location_name',
    'location_address', 'map_url', 'event_date', 'start_time', 'end_time',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'state' => 'draft',
        'max_capacity' => 0,
        'require_approval' => false,
        'registration_open' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => EventState::class,
            'max_capacity' => 'integer',
            'require_approval' => 'boolean',
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

    public function acceptsPublicRegistrations(): bool
    {
        return $this->state === EventState::Published && $this->registration_open;
    }

    /** Temporary signed link to the client's preview of the invitation. */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute(
            'site.preview',
            now()->addMinutes((int) config('emp.preview_link_minutes')),
            ['event' => $this->id],
        );
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

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
