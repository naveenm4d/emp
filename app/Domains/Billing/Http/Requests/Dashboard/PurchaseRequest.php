<?php

namespace App\Domains\Billing\Http\Requests\Dashboard;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Models\ClientUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Something the client buys. Pays with one of the account's saved cards
 * (payment_method_id) or, without one, on PayHere's page. The price is
 * never part of the request.
 */
abstract class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('create', Payment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var ClientUser $user */
        $user = $this->user('client');

        return [
            'payment_method_id' => [
                'nullable', 'uuid',
                Rule::exists(PaymentMethod::class, 'id')->where('client_id', $user->client_id)->whereNull('deleted_at'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['payment_method_id.exists' => 'Choose one of your saved cards.'];
    }

    /** The saved card to charge, or null to pay on PayHere's page. */
    public function card(): ?PaymentMethod
    {
        $id = $this->validated('payment_method_id');

        return is_string($id) ? PaymentMethod::query()->whereKey($id)->first() : null;
    }
}
