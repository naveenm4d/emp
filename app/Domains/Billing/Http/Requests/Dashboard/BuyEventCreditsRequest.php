<?php

namespace App\Domains\Billing\Http\Requests\Dashboard;

/** Buying Celebration event credits. */
class BuyEventCreditsRequest extends PurchaseRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function quantity(): int
    {
        return (int) $this->validated('quantity');
    }
}
