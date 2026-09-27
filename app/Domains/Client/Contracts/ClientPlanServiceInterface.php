<?php

namespace App\Domains\Client\Contracts;

use App\Domains\Client\DTOs\ClientPlanData;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Client\Models\Client;
use App\Domains\Staff\Models\StaffMember;

/**
 * What a client's plan allows, and staff changing it (after payment).
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
     *     max_guests_per_event: int|null, allows_extra_guests: bool, features: list<string>,
     *     message_limits: array{invitations: int, reminders: int},
     * }
     */
    public function usage(Client $client): array;

    /** Staff set the plan, its end date and event credits; logged with who and why. */
    public function changePlan(Client $client, ClientPlanData $data, StaffMember $by, string $note): Client;
}
