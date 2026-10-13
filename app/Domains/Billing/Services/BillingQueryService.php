<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\BillingQueryServiceInterface;
use App\Domains\Billing\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Contracts\PaymentRepositoryInterface;
use App\Domains\Billing\DTOs\PaymentFilters;
use App\Domains\Client\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BillingQueryService implements BillingQueryServiceInterface
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function payments(Client $client, int $perPage = 20): LengthAwarePaginator
    {
        return $this->payments->forClient($client->id, $perPage);
    }

    public function recentPayments(Client $client, int $limit = 5): Collection
    {
        return $this->payments->latestForClient($client->id, $limit);
    }

    public function search(PaymentFilters $filters): LengthAwarePaginator
    {
        return $this->payments->search($filters, 30);
    }

    public function usesMockGateway(): bool
    {
        return $this->gateway->isMock();
    }
}
