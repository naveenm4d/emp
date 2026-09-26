<?php

namespace App\Domains\Client\Models;

use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Event\Models\Event;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A subscribed customer of the platform who creates and manages events.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property ClientPlan $plan
 * @property CarbonImmutable|null $plan_expires_at when a subscription (Business, Enterprise) ends; null = no end date
 * @property int $event_credits Celebration events paid for and not used yet
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(ClientFactory::class)]
#[Fillable(['name', 'email', 'email_verified_at', 'password', 'plan', 'plan_expires_at', 'event_credits'])]
#[Hidden(['password', 'remember_token'])]
class Client extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<ClientFactory> */
    use CanResetPassword, HasFactory, HasUuids, Notifiable;

    protected $attributes = [
        'plan' => 'starter',
        'event_credits' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plan' => ClientPlan::class,
            'plan_expires_at' => 'datetime',
            'event_credits' => 'integer',
        ];
    }

    /** A subscription plan that hasn't ended (plans without subscriptions are always active). */
    public function planActive(): bool
    {
        return ! $this->plan->isSubscription()
            || $this->plan_expires_at === null
            || $this->plan_expires_at->isFuture();
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
