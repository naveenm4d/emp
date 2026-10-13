<?php

namespace App\Domains\Billing\Http\Requests\Dashboard;

use App\Domains\Billing\Enums\BillingInterval;
use Illuminate\Validation\Rule;

/** Subscribing to Business, monthly or yearly. */
class SubscribeRequest extends PurchaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'interval' => ['required', Rule::enum(BillingInterval::class)],
        ];
    }

    public function billingInterval(): BillingInterval
    {
        return BillingInterval::from((string) $this->validated('interval'));
    }
}
