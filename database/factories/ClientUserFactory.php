<?php

namespace Database\Factories;

use App\Domains\Client\Enums\ClientPermission;
use App\Domains\Client\Enums\ClientUserRole;
use App\Domains\Client\Models\Client;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Event\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A member who accepted the invitation, with no permissions and no events.
 *
 * @extends Factory<ClientUser>
 */
class ClientUserFactory extends Factory
{
    protected $model = ClientUser::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => ClientUserRole::Member,
            'permissions' => [],
            'all_events' => false,
            'invited_at' => now(),
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => ClientUserRole::Owner, 'all_events' => true, 'invited_at' => null]);
    }

    /** @param list<ClientPermission> $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->state(['permissions' => array_map(fn (ClientPermission $permission) => $permission->value, $permissions)]);
    }

    public function allEvents(): static
    {
        return $this->state(['all_events' => true]);
    }

    /** Invited and hasn't set a password yet. */
    public function invited(): static
    {
        return $this->state(['password' => null, 'email_verified_at' => null, 'joined_at' => null]);
    }

    /** Gives the member access to these events (of the same account). */
    public function forEvents(Event ...$events): static
    {
        return $this->afterCreating(fn (ClientUser $user) => $user->events()->attach(array_map(fn (Event $event) => $event->id, $events)));
    }
}
