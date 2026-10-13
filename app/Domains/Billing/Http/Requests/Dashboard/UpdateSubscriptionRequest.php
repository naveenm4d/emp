<?php

namespace App\Domains\Billing\Http\Requests\Dashboard;

use App\Domains\Billing\Enums\BillingInterval;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Auto-renew off / on, monthly ↔ yearly, or the card renewals use. */
class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller authorizes the client's subscription
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ClientUser $user */
        $user = $this->user('client');

        return [
            'action' => ['required', Rule::in(['cancel', 'resume', 'interval', 'card'])],
            'interval' => ['required_if:action,interval', 'nullable', Rule::enum(BillingInterval::class)],
            'payment_method_id' => [
                'required_if:action,card', 'nullable', 'uuid',
                Rule::exists(PaymentMethod::class, 'id')->where('client_id', $user->client_id)->whereNull('deleted_at'),
            ],
        ];
    }

    public function action(): string
    {
        return (string) $this->validated('action');
    }

    public function billingInterval(): BillingInterval
    {
        return BillingInterval::from((string) $this->validated('interval'));
    }

    public function card(): PaymentMethod
    {
        /** @var PaymentMethod */
        return PaymentMethod::query()->findOrFail($this->validated('payment_method_id'));
    }
}
