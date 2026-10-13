<?php

namespace App\Domains\Billing\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Billing\Contracts\PaymentMethodRepositoryInterface;
use App\Domains\Billing\Models\PaymentMethod;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<PaymentMethod>
 */
class PaymentMethodRepository extends BaseRepository implements PaymentMethodRepositoryInterface
{
    protected function model(): string
    {
        return PaymentMethod::class;
    }

    public function forClient(string $clientId): Collection
    {
        return $this->query()
            ->where('client_id', $clientId)
            ->orderByDesc('is_default')
            ->latest()
            ->get();
    }

    public function defaultFor(string $clientId): ?PaymentMethod
    {
        return $this->query()->where('client_id', $clientId)->where('is_default', true)->first();
    }

    public function makeDefault(PaymentMethod $card): void
    {
        $this->query()
            ->where('client_id', $card->client_id)
            ->whereKeyNot($card->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $card->forceFill(['is_default' => true])->save();
    }
}
