<?php

namespace App\Domains\Client\Models;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Enums\ClientUserRole;
use App\Domains\Event\Models\Event;
use Carbon\CarbonImmutable;
use Database\Factories\ClientUserFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Someone who signs in to a client account (the `client` guard). The owner
 * can do everything; members have the permissions and events they were given.
 *
 * @property string $id
 * @property string $client_id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property ClientUserRole $role
 * @property list<string> $permissions
 * @property bool $all_events members only: sees every event of the account
 * @property CarbonImmutable|null $invited_at
 * @property CarbonImmutable|null $joined_at null = invitation not accepted yet
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Client $client
 */
#[UseFactory(ClientUserFactory::class)]
#[Fillable([
    'client_id', 'name', 'email', 'email_verified_at', 'password', 'role', 'permissions', 'all_events',
    'invited_at', 'joined_at', 'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class ClientUser extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<ClientUserFactory> */
    use CanResetPassword, HasFactory, HasUuids, Notifiable;

    /** @var array<string, bool> event id => whether the member was given it (per request) */
    private array $eventAccess = [];

    protected $attributes = [
        'role' => 'member',
        'permissions' => '[]',
        'all_events' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => ClientUserRole::class,
            'permissions' => 'array',
            'all_events' => 'boolean',
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The events a member was given (unused for the owner and `all_events` members).
     *
     * @return BelongsToMany<Event, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'client_user_event');
    }

    public function isOwner(): bool
    {
        return $this->role === ClientUserRole::Owner;
    }

    /** Still has to accept the invitation (set a password). */
    public function isPending(): bool
    {
        return $this->joined_at === null;
    }

    public function hasPermission(ClientPermission $permission): bool
    {
        return $this->isOwner() || in_array($permission->value, $this->permissions, true);
    }

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        return $this->isOwner() ? ClientPermission::values() : $this->permissions;
    }

    public function seesAllEvents(): bool
    {
        return $this->isOwner() || $this->all_events;
    }

    /** The event belongs to the account and the user can see it. */
    public function canAccessEvent(Event $event): bool
    {
        if ($event->client_id !== $this->client_id) {
            return false;
        }

        return $this->seesAllEvents()
            || ($this->eventAccess[$event->id] ??= $this->events()->whereKey($event->id)->exists());
    }
}
