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
        /** Enterprise: what an online renewal costs (minor units) and how many months it covers. */
        public ?int $renewalPrice = null,
        public ?int $renewalMonths = null,
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
            // Entered in rupees; only Enterprise is renewed at a price staff set.
            renewalPrice: $plan === ClientPlan::Enterprise && isset($data['renewal_price']) && $data['renewal_price'] !== ''
                ? (int) round(((float) $data['renewal_price']) * 100)
                : null,
            renewalMonths: $plan === ClientPlan::Enterprise && ! empty($data['renewal_months']) ? (int) $data['renewal_months'] : null,
        );
    }
}
