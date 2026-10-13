<?php

namespace App\Domains\Billing\Contracts;

use App\Domains\Billing\DTOs\PaymentFilters;
use App\Domains\Billing\Models\Payment;
use App\Domains\Client\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Reading payments for the dashboard and the admin console. */
interface BillingQueryServiceInterface
{
    /** @return LengthAwarePaginator<int, Payment> */
    public function payments(Client $client, int $perPage = 20): LengthAwarePaginator;

    /** @return Collection<int, Payment> */
    public function recentPayments(Client $client, int $limit = 5): Collection;

    /** @return LengthAwarePaginator<int, Payment> */
    public function search(PaymentFilters $filters): LengthAwarePaginator;

    /** Payments go to the mock page (no PayHere credentials). */
    public function usesMockGateway(): bool;
}
