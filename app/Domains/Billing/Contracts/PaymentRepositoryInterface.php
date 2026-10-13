<?php

namespace App\Domains\Billing\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Billing\DTOs\PaymentFilters;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<Payment>
 */
interface PaymentRepositoryInterface extends RepositoryInterface
{
    public function findByOrderId(string $orderId): ?Payment;

    /** Loads the payment with a row lock. Must be called inside a transaction. */
    public function findAndLock(string $id): Payment;

    /** @return LengthAwarePaginator<int, Payment> the client's payments, newest first */
    public function forClient(string $clientId, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, Payment> the client's latest payments */
    public function latestForClient(string $clientId, int $limit): Collection;

    /** @return LengthAwarePaginator<int, Payment> every payment, for staff */
    public function search(PaymentFilters $filters, int $perPage): LengthAwarePaginator;

    /** The renewal charge for a subscription's period, if one was started. */
    public function renewalFor(string $subscriptionId, CarbonImmutable $periodEnd): ?Payment;

    /** @return Collection<int, Payment> paid payments whose purchase wasn't applied (an error), to retry */
    public function unfulfilled(): Collection;

    /**
     * Logs a call to the notify URL.
     *
     * @param  array<string, mixed>  $payload
     */
    public function logNotification(?string $orderId, array $payload, bool $signatureValid, ?string $error = null): PaymentNotification;
}
