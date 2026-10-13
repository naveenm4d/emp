<?php

namespace App\Domains\Client\Contracts;

use App\Domains\Client\DTOs\ClientPlanData;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Client\Models\Client;
use App\Domains\Staff\Models\StaffMember;

/**
 * What a client's plan allows; staff changing it, and purchases applying it.
 */
interface ClientPlanServiceInterface
{
    /** Throws EventQuotaReachedException when the plan allows no new event. */
    public function ensureCanCreateEvent(Client $client): void;

    /** Uses up one event credit (Celebration). Call inside the event's transaction. */
    public function useEventCredit(Client $client): void;

    /** Whether the client's plan includes the feature. */
    public function hasFeature(Client $client, PlanFeature $feature): bool;

    /** Throws PlanFeatureUnavailableException when the plan doesn't include the feature. */
    public function ensureFeature(Client $client, PlanFeature $feature): void;

    /**
     * The plan and what's used / left, for the dashboard and admin.
     *
     * @return array{
     *     plan: string, label: string, active: bool, expires_at: string|null, event_credits: int,
     *     events_used: int, events_allowed: int|null, can_create_event: bool, reason: string|null,
     *     max_guests_per_event: int|null, users_used: int, users_allowed: int|null, allows_extra_guests: bool, features: list<string>,
     *     message_limits: array{invitations: int, reminders: int},
     *     scheduled_plan: string|null, scheduled_plan_label: string|null,
     *     renewal_price: int|null, renewal_months: int|null, can_renew_online: bool,
     * }
     */
    public function usage(Client $client): array;

    /** Staff set the plan, its end date, event credits and user limit; logged with who and why. */
    public function changePlan(Client $client, ClientPlanData $data, StaffMember $by, string $note): Client;

    /**
     * A payment or a billing rule changed the plan (subscription, renewal, event
     * credits, a scheduled downgrade). Logged in the plan history without a staff
     * member; $reference says why. Clears any scheduled downgrade.
     */
    public function applyPurchase(Client $client, ClientPlanData $data, string $reference): Client;

    /** An Enterprise renewal was paid: the plan runs for its renewal months from now. */
    public function renewEnterprise(Client $client, string $reference): Client;

    /**
     * Downgrade when the current plan ends (Business → Celebration / Starter), or
     * null to keep the plan. Throws PlanChangeNotAllowedException otherwise.
     */
    public function scheduleChange(Client $client, ?ClientPlan $plan): Client;

    /** Applies the downgrades whose plan has ended; returns how many. */
    public function applyScheduledChanges(): int;

    /** Adds paid event credits; Starter (or an ended plan) moves to Celebration. */
    public function addEventCredits(Client $client, int $credits, string $reference): Client;
}
