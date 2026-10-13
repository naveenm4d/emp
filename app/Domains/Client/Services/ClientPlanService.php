<?php

namespace App\Domains\Client\Services;

use App\Core\Services\BaseService;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\DTOs\ClientPlanData;
use App\Domains\Client\Enums\ClientPlan;
use App\Domains\Client\Enums\PlanFeature;
use App\Domains\Client\Events\ClientPlanChanged;
use App\Domains\Client\Exceptions\EventQuotaReachedException;
use App\Domains\Client\Exceptions\PlanChangeNotAllowedException;
use App\Domains\Client\Exceptions\PlanFeatureUnavailableException;
use App\Domains\Client\Models\Client;
use App\Domains\Staff\Contracts\StaffActivityServiceInterface;
use App\Domains\Staff\Models\StaffMember;
use Carbon\CarbonImmutable;

class ClientPlanService extends BaseService implements ClientPlanServiceInterface
{
    /** Activity log action for plan changes a payment made (staff changes are admin.clients.plan). */
    public const string PURCHASE_ACTION = 'billing.plan';

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
            'scheduled_plan' => $client->scheduled_plan?->value,
            'scheduled_plan_label' => $client->scheduled_plan?->label(),
            'renewal_price' => $client->renewal_price,
            'renewal_months' => $client->renewal_months,
            'can_renew_online' => $client->canRenewOnline(),
        ];
    }

    public function changePlan(Client $client, ClientPlanData $data, StaffMember $by, string $note): Client
    {
        return $this->transaction(function () use ($client, $data, $by, $note) {
            $client = $this->apply($client, $data, $by, 'admin.clients.plan', $note);

            ClientPlanChanged::dispatch($client);

            return $client;
        });
    }

    public function applyPurchase(Client $client, ClientPlanData $data, string $reference): Client
    {
        return $this->transaction(fn () => $this->apply($client, $data, null, self::PURCHASE_ACTION, $reference));
    }

    public function addEventCredits(Client $client, int $credits, string $reference): Client
    {
        return $this->transaction(function () use ($client, $credits, $reference) {
            $locked = $this->clients->findAndLock($client->id);
            // Credits only count on Celebration: Starter, or a subscription that ended, moves over.
            $keepsPlan = $locked->plan === ClientPlan::Celebration;

            return $this->apply($locked, new ClientPlanData(
                plan: ClientPlan::Celebration,
                expiresAt: null,
                eventCredits: ($keepsPlan ? $locked->event_credits : 0) + $credits,
                userLimit: $locked->user_limit,
            ), null, self::PURCHASE_ACTION, $reference);
        });
    }

    public function renewEnterprise(Client $client, string $reference): Client
    {
        return $this->transaction(function () use ($client, $reference) {
            $locked = $this->clients->findAndLock($client->id);

            if ($locked->plan !== ClientPlan::Enterprise || $locked->renewal_months === null) {
                throw new PlanChangeNotAllowedException('Only an Enterprise plan with renewal terms can be renewed this way.');
            }

            // Renewed before it ended (a second payment): the new period follows the current one.
            $start = $locked->plan_expires_at?->isFuture() ? CarbonImmutable::parse($locked->plan_expires_at) : CarbonImmutable::now();

            return $this->apply($locked, new ClientPlanData(
                plan: ClientPlan::Enterprise,
                expiresAt: $start->addMonthsNoOverflow($locked->renewal_months)->endOfDay(),
                eventCredits: $locked->event_credits,
                userLimit: $locked->user_limit,
                renewalPrice: $locked->renewal_price,
                renewalMonths: $locked->renewal_months,
            ), null, self::PURCHASE_ACTION, $reference);
        });
    }

    public function scheduleChange(Client $client, ?ClientPlan $plan): Client
    {
        if ($plan !== null) {
            if ($client->plan !== ClientPlan::Business || ! $client->planActive() || $client->plan_expires_at === null) {
                throw new PlanChangeNotAllowedException('Only a running Business plan can be downgraded.');
            }

            if (! in_array($plan, [ClientPlan::Celebration, ClientPlan::Starter], true)) {
                throw new PlanChangeNotAllowedException('Business can be downgraded to Celebration or Starter.');
            }
        }

        return $this->transaction(function () use ($client, $plan) {
            $before = $client->scheduled_plan;

            /** @var Client $client */
            $client = $this->clients->update($client, ['scheduled_plan' => $plan]);

            if ($before !== $plan) {
                $this->activities->record(
                    staff: null,
                    action: self::PURCHASE_ACTION,
                    description: $plan
                        ? "Downgrade to {$plan->label()} when Business ends on {$client->plan_expires_at?->format('j M Y')}"
                        : 'Kept Business (downgrade to '.$before?->label().' canceled)',
                    subject: $client,
                    clientId: $client->id,
                    changes: ['before' => ['scheduled_plan' => $before?->value], 'after' => ['scheduled_plan' => $plan?->value]],
                );
            }

            return $client;
        });
    }

    public function applyScheduledChanges(): int
    {
        $applied = 0;

        foreach ($this->clients->dueForScheduledChange(CarbonImmutable::now()) as $client) {
            $this->transaction(function () use ($client) {
                $locked = $this->clients->findAndLock($client->id);
                $plan = $locked->scheduled_plan;

                if ($plan === null || $locked->planActive()) {
                    return;
                }

                $this->apply($locked, new ClientPlanData(
                    plan: $plan,
                    expiresAt: null,
                    eventCredits: $plan === ClientPlan::Celebration ? $locked->event_credits : 0,
                    userLimit: null,
                ), null, self::PURCHASE_ACTION, "Downgrade to {$plan->label()} after Business ended");
            });
            $applied++;
        }

        return $applied;
    }

    /** Sets the plan and logs the change (before / after) with who did it, or null for a purchase. */
    private function apply(Client $client, ClientPlanData $data, ?StaffMember $by, string $action, string $note): Client
    {
        $before = $this->snapshot($client);

        /** @var Client $client */
        $client = $this->clients->update($client, [
            'plan' => $data->plan,
            'plan_expires_at' => $data->expiresAt,
            'event_credits' => $data->eventCredits,
            'user_limit' => $data->userLimit,
            'renewal_price' => $data->renewalPrice,
            'renewal_months' => $data->renewalMonths,
            // Any plan change replaces a downgrade that was waiting.
            'scheduled_plan' => null,
        ]);

        $after = $this->snapshot($client);

        $this->activities->record(
            staff: $by,
            action: $action,
            description: $this->describeChange($before, $after),
            subject: $client,
            clientId: $client->id,
            changes: ['before' => $before, 'after' => $after],
            note: $note,
        );

        return $client;
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

    /** @return array{plan: string, plan_expires_at: string|null, event_credits: int, user_limit: int|null, renewal_price: int|null, renewal_months: int|null} */
    private function snapshot(Client $client): array
    {
        return [
            'plan' => $client->plan->value,
            'plan_expires_at' => $client->plan_expires_at?->toDateString(),
            'event_credits' => $client->event_credits,
            'user_limit' => $client->user_limit,
            'renewal_price' => $client->renewal_price,
            'renewal_months' => $client->renewal_months,
        ];
    }

    /**
     * @param  array{plan: string, plan_expires_at: string|null, event_credits: int, user_limit: int|null, renewal_price: int|null, renewal_months: int|null}  $before
     * @param  array{plan: string, plan_expires_at: string|null, event_credits: int, user_limit: int|null, renewal_price: int|null, renewal_months: int|null}  $after
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

        if ($after['renewal_price'] !== null && ($before['renewal_price'] !== $after['renewal_price'] || $before['renewal_months'] !== $after['renewal_months'])) {
            $parts[] = 'renews online for Rs. '.number_format($after['renewal_price'] / 100)." per {$after['renewal_months']} months";
        }

        return implode(', ', $parts);
    }
}
