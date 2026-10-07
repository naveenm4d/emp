<?php

namespace App\Domains\Client\Services;

use App\Core\Services\BaseService;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\DTOs\ClientPlanData;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Client\Exceptions\EventQuotaReachedException;
use App\Domains\Client\Exceptions\PlanFeatureUnavailableException;
use App\Domains\Client\Models\Client;
use App\Domains\Staff\Contracts\StaffActivityServiceInterface;
use App\Domains\Staff\Models\StaffMember;

class ClientPlanService extends BaseService implements ClientPlanServiceInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients,
        private readonly StaffActivityServiceInterface $activities,
    ) {}

    public function ensureCanCreateEvent(Client $client): void
    {
        $reason = $this->eventBlockedReason($client);

        if ($reason !== null) {
            throw new EventQuotaReachedException($reason);
        }
    }

    public function useEventCredit(Client $client): void
    {
        if (! $client->plan->usesEventCredits()) {
            return;
        }

        $locked = $this->clients->findAndLock($client->id);

        if ($locked->event_credits < 1) {
            throw new EventQuotaReachedException($this->eventBlockedReason($locked));
        }

        $this->clients->update($locked, ['event_credits' => $locked->event_credits - 1]);
        $client->event_credits = $locked->event_credits;
    }

    public function hasFeature(Client $client, PlanFeature $feature): bool
    {
        return $client->plan->hasFeature($feature);
    }

    public function ensureFeature(Client $client, PlanFeature $feature): void
    {
        if (! $this->hasFeature($client, $feature)) {
            throw PlanFeatureUnavailableException::for($feature);
        }
    }

    public function usage(Client $client): array
    {
        $plan = $client->plan;
        $reason = $this->eventBlockedReason($client);

        return [
            'plan' => $plan->value,
            'label' => $plan->label(),
            'active' => $client->planActive(),
            'expires_at' => $client->plan_expires_at?->toIso8601String(),
            'event_credits' => $client->event_credits,
            'events_used' => $this->clients->eventsCreatedCount($client->id),
            'events_allowed' => $plan === ClientPlan::Starter ? 1 : null,
            'can_create_event' => $reason === null,
            'reason' => $reason,
            'max_guests_per_event' => $plan->maxGuestsPerEvent(),
            'users_used' => $this->clients->usersCount($client->id),
            'users_allowed' => $client->userLimit(),
            'allows_extra_guests' => $plan->allowsExtraGuests(),
            'features' => array_map(fn (PlanFeature $feature) => $feature->value, $plan->features()),
            'message_limits' => $plan->messageLimits() ?? [
                'invitations' => (int) config('emp.max_invitations_per_guest'),
                'reminders' => (int) config('emp.max_reminders_per_guest'),
            ],
        ];
    }

    public function changePlan(Client $client, ClientPlanData $data, StaffMember $by, string $note): Client
    {
        return $this->transaction(function () use ($client, $data, $by, $note) {
            $before = $this->snapshot($client);

            /** @var Client $client */
            $client = $this->clients->update($client, [
                'plan' => $data->plan,
                'plan_expires_at' => $data->expiresAt,
                'event_credits' => $data->eventCredits,
                'user_limit' => $data->userLimit,
            ]);

            $after = $this->snapshot($client);

            $this->activities->record(
                staff: $by,
                action: 'admin.clients.plan',
                description: $this->describeChange($before, $after),
                subject: $client,
                clientId: $client->id,
                changes: ['before' => $before, 'after' => $after],
                note: $note,
            );

            return $client;
        });
    }

    /** Why the client can't create an event now, or null when they can. */
    private function eventBlockedReason(Client $client): ?string
    {
        return match (true) {
            $client->plan === ClientPlan::Starter && $this->clients->eventsCreatedCount($client->id) >= 1 => 'The Starter plan includes one event. Upgrade to Celebration or Business to create more.',
            $client->plan->usesEventCredits() && $client->event_credits < 1 => 'You have no event credits left. Buy another event to create one.',
            $client->plan->isSubscription() && ! $client->planActive() => "Your {$client->plan->label()} plan ended on {$client->plan_expires_at?->format('j M Y')}. Renew it to create new events.",
            default => null,
        };
    }

    /** @return array{plan: string, plan_expires_at: string|null, event_credits: int, user_limit: int|null} */
    private function snapshot(Client $client): array
    {
        return [
            'plan' => $client->plan->value,
            'plan_expires_at' => $client->plan_expires_at?->toDateString(),
            'event_credits' => $client->event_credits,
            'user_limit' => $client->user_limit,
        ];
    }

    /**
     * @param  array{plan: string, plan_expires_at: string|null, event_credits: int, user_limit: int|null}  $before
     * @param  array{plan: string, plan_expires_at: string|null, event_credits: int, user_limit: int|null}  $after
     */
    private function describeChange(array $before, array $after): string
    {
        $label = fn (string $plan) => ClientPlan::from($plan)->label();
        $parts = [];

        $parts[] = $before['plan'] === $after['plan']
            ? "Plan {$label($after['plan'])}"
            : "Plan {$label($before['plan'])} → {$label($after['plan'])}";

        if ($before['plan_expires_at'] !== $after['plan_expires_at']) {
            $parts[] = 'active until '.($after['plan_expires_at'] ?? 'no end date');
        }

        if ($before['event_credits'] !== $after['event_credits']) {
            $parts[] = "event credits {$before['event_credits']} → {$after['event_credits']}";
        }

        if ($before['user_limit'] !== $after['user_limit']) {
            $parts[] = 'users '.($after['user_limit'] ?? 'plan default');
        }

        return implode(', ', $parts);
    }
}
