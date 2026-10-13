<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\SubscriptionServiceInterface;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Models\Payment;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Contracts\TemplateServiceInterface;
use App\Domains\Template\Models\Template;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * What a payment bought, applied to the account through each domain's own
 * service (plan, event, template). PaymentService calls it once per payment.
 */
class FulfilmentService
{
    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptions,
        private readonly ClientPlanServiceInterface $plans,
        private readonly EventServiceInterface $events,
        private readonly TemplateServiceInterface $templates,
    ) {}

    public function fulfil(Payment $payment): void
    {
        $reference = "Payment {$payment->order_id}";

        match ($payment->purpose) {
            PaymentPurpose::Subscription => $this->subscriptions->activate($payment),
            PaymentPurpose::Renewal => $this->subscriptions->renewed($payment),
            PaymentPurpose::EventCredits => $this->plans->addEventCredits($payment->client, (int) ($payment->details['quantity'] ?? 1), $reference),
            PaymentPurpose::ExtraGuests => $this->addExtraGuests($payment, $reference),
            PaymentPurpose::Template => $this->grantTemplate($payment),
            PaymentPurpose::EnterpriseRenewal => $this->plans->renewEnterprise($payment->client, $reference),
            PaymentPurpose::CardSetup => null,
        };
    }

    public function failed(Payment $payment): void
    {
        if ($payment->purpose === PaymentPurpose::Renewal && $payment->subscription !== null && $payment->renewal_period_end !== null) {
            $this->subscriptions->renewalFailed($payment->subscription, CarbonImmutable::parse($payment->renewal_period_end));
        }
    }

    private function addExtraGuests(Payment $payment, string $reference): void
    {
        $event = $payment->subject;

        if (! $event instanceof Event) {
            Log::warning('Extra guests were paid for an event that no longer exists.', ['order_id' => $payment->order_id]);

            return;
        }

        $this->events->addExtraGuests($event, (int) ($payment->details['blocks'] ?? 0), $reference);
    }

    private function grantTemplate(Payment $payment): void
    {
        $template = $payment->subject;

        if (! $template instanceof Template) {
            Log::warning('A design was paid for that no longer exists.', ['order_id' => $payment->order_id]);

            return;
        }

        $this->templates->grantToClient($template, $payment->client, $payment->id);
    }
}
