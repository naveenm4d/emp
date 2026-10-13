<?php

namespace App\Domains\Billing\Http\Requests\Dashboard;

use App\Domains\Event\Models\Event;

/** Buying blocks of extra guests for an event the user can see. */
class BuyExtraGuestsRequest extends PurchaseRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return parent::authorize()
            && $event instanceof Event
            && ($this->user('client')?->can('view', $event) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'blocks' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function blocks(): int
    {
        return (int) $this->validated('blocks');
    }
}
