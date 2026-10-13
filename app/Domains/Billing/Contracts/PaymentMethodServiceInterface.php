<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\DTOs\CardDetails;
use App\Domains\Billing\Models\PaymentMethod;
use App\Domains\Client\Models\Client;
use Illuminate\Support\Collection;

/** The cards a client saved with PayHere. */
interface PaymentMethodServiceInterface
{
    /** @return Collection<int, PaymentMethod> the default first */
    public function forClient(Client $client): Collection;

    /** Keeps a card PayHere saved (the same card saved again gets the new token). The first card becomes the default. */
    public function store(Client $client, CardDetails $card, ?string $addedBy): PaymentMethod;

    /** The default card is used for one-click purchases and for renewals. */
    public function makeDefault(PaymentMethod $card): PaymentMethod;

    /**
     * Forgets the card (its token is wiped). Renewals move to the new default card,
     * or auto-renew turns off when there is none.
     *
     * @return string|null a warning for the client (auto-renew turned off)
     */
    public function remove(PaymentMethod $card): ?string;
}
