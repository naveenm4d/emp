<?php

namespace App\Domains\Client\Models;

use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Enums\ClientUserRole;
use App\Domains\Event\Models\Event;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer account of the platform that creates and manages events. People
 * sign in as one of its users (ClientUser); the first one is the owner.
 *
 * @property string $id
 * @property string $name
 * @property string $email the account's contact email
 * @property ClientPlan $plan
 * @property CarbonImmutable|null $plan_expires_at when a subscription (Business, Enterprise) ends; null = no end date
 * @property int $event_credits Celebration events paid for and not used yet
 * @property int|null $user_limit staff override of the plan's user limit
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read ClientUser|null $owner
 */
#[UseFactory(ClientFactory::class)]
#[Fillable(['name', 'email', 'plan', 'plan_expires_at', 'event_credits', 'user_limit'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'plan' => 'starter',
        'event_credits' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan' => ClientPlan::class,
            'plan_expires_at' => 'datetime',
            'event_credits' => 'integer',
            'user_limit' => 'integer',
        ];
    }

    /** A subscription plan that hasn't ended (plans without subscriptions are always active). */
    public function planActive(): bool
    {
        return ! $this->plan->isSubscription()
            || $this->plan_expires_at === null
            || $this->plan_expires_at->isFuture();
    }

    /** How many users the account can have, the owner and pending invitations included; null = unlimited. */
    public function userLimit(): ?int
    {
        return $this->user_limit ?? $this->plan->maxUsers();
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<ClientUser, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(ClientUser::class);
    }

    /** @return HasOne<ClientUser, $this> */
    public function owner(): HasOne
    {
        return $this->hasOne(ClientUser::class)->where('role', ClientUserRole::Owner);
    }
}
