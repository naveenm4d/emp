<?php

namespace App\Domains\Billing\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Billing\Models\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<PaymentMethod>
 */
interface PaymentMethodRepositoryInterface extends RepositoryInterface
{
    /** @return Collection<int, PaymentMethod> the client's saved cards, the default first */
    public function forClient(string $clientId): Collection;

    public function defaultFor(string $clientId): ?PaymentMethod;

    /** Makes one card the default and no other. */
    public function makeDefault(PaymentMethod $card): void;
}
