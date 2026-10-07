<?php

namespace App\Domains\Client\Enums;

use App\Core\Enums\Concerns\HasValues;

/**
 * What a client pays for and what it includes. Staff set it after payment
 * (there is no online payment yet); new sign-ups start on Starter.
 */
enum ClientPlan: string
{
    use HasValues;

    /** Free: one event, ever. */
    case Starter = 'starter';
    /** Paid per event: each event uses one event credit. */
    case Celebration = 'celebration';
    /** Monthly subscription for professional organizers and companies. */
    case Business = 'business';
    /** Custom subscription for large organizations. */
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'Starter',
            self::Celebration => 'Celebration',
            self::Business => 'Business',
            self::Enterprise => 'Enterprise',
        };
    }

    /** Most guests an event can have (before extra guests); null = unlimited. */
    public function maxGuestsPerEvent(): ?int
    {
        return match ($this) {
            self::Starter => 50,
            self::Celebration => 500,
            self::Business => 2000,
            self::Enterprise => null,
        };
    }

    /**
     * How many people can sign in to the account, the owner included; null =
     * set per client by staff (clients.user_limit), unlimited when not set.
     */
    public function maxUsers(): ?int
    {
        return match ($this) {
            self::Starter => 1,
            self::Celebration => 2,
            self::Business => 5,
            self::Enterprise => null,
        };
    }

    /** Extra guests can be bought per event, in blocks (config emp.extra_guests_block). */
    public function allowsExtraGuests(): bool
    {
        return $this === self::Celebration || $this === self::Business;
    }

    /** Paid by the month (or year) and active until plan_expires_at. */
    public function isSubscription(): bool
    {
        return $this === self::Business || $this === self::Enterprise;
    }

    /** Each new event uses one of the client's event credits. */
    public function usesEventCredits(): bool
    {
        return $this === self::Celebration;
    }

    /**
     * Invitations / reminders each guest can get, when the plan differs from
     * the platform default (config emp.max_*_per_guest).
     *
     * @return array{invitations: int, reminders: int}|null
     */
    public function messageLimits(): ?array
    {
        return match ($this) {
            self::Starter => ['invitations' => 1, 'reminders' => 1],
            self::Enterprise => ['invitations' => 5, 'reminders' => 5],
            default => null,
        };
    }

    public function hasFeature(PlanFeature $feature): bool
    {
        return $this !== self::Starter;
    }

    /** @return list<PlanFeature> */
    public function features(): array
    {
        return array_values(array_filter(PlanFeature::cases(), fn (PlanFeature $feature) => $this->hasFeature($feature)));
    }
}
