<?php

namespace App\Domains\Client\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Client\Enums\ClientPlan;
use Carbon\CarbonImmutable;

/** A client's plan as staff set it after payment. */
final readonly class ClientPlanData extends DataTransferObject
{
    public function __construct(
        public ClientPlan $plan,
        public ?CarbonImmutable $expiresAt,
        public int $eventCredits,
        public ?int $userLimit = null,
    ) {}

    /** @param array<string, mixed> $data validated input */
    public static function fromArray(array $data): self
    {
        $plan = ClientPlan::from($data['plan']);

        return new self(
            plan: $plan,
            // Only subscriptions end; other plans ignore the date.
            expiresAt: $plan->isSubscription() && ! empty($data['plan_expires_at'])
                ? CarbonImmutable::parse($data['plan_expires_at'])->endOfDay()
                : null,
            eventCredits: (int) ($data['event_credits'] ?? 0),
            // Overrides the plan's user limit; Enterprise has none of its own.
            userLimit: isset($data['user_limit']) ? (int) $data['user_limit'] : null,
        );
    }
}
