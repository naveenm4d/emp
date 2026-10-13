<?php

namespace App\Domains\Billing\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Billing\Contracts\PaymentRepositoryInterface;
use App\Domains\Billing\DTOs\PaymentFilters;
use App\Domains\Billing\Enums\PaymentPurpose;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\PaymentNotification;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<Payment>
 */
class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    protected function model(): string
    {
        return Payment::class;
    }

    public function findByOrderId(string $orderId): ?Payment
    {
        return $this->query()->where('order_id', $orderId)->first();
    }

    public function findAndLock(string $id): Payment
    {
        /** @var Payment */
        return $this->query()->lockForUpdate()->findOrFail($id);
    }

    public function forClient(string $clientId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with('paymentMethod')
            ->where('client_id', $clientId)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function latestForClient(string $clientId, int $limit): Collection
    {
        return $this->query()
            ->with('paymentMethod')
            ->where('client_id', $clientId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function search(PaymentFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with(['client:id,name,email', 'paymentMethod'])
            ->when($filters->status, fn (Builder $query, PaymentStatus $status) => $query->where('status', $status))
            ->when($filters->purpose, fn (Builder $query, $purpose) => $query->where('purpose', $purpose))
            ->when($filters->clientId, fn (Builder $query, string $id) => $query->where('client_id', $id))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('order_id', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('payhere_payment_id', "%{$search}%", caseSensitive: false)
                    ->orWhereHas('client', fn (Builder $query) => $query
                        ->whereLike('name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('email', "%{$search}%", caseSensitive: false)),
            ))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function renewalFor(string $subscriptionId, CarbonImmutable $periodEnd): ?Payment
    {
        return $this->query()
            ->where('subscription_id', $subscriptionId)
            ->where('renewal_period_end', $periodEnd)
            ->first();
    }

    public function unfulfilled(): Collection
    {
        return $this->query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereNull('fulfilled_at')
            ->where('purpose', '!=', PaymentPurpose::CardSetup)
            ->where('created_at', '>', now()->subDays(30))
            ->get();
    }

    public function logNotification(?string $orderId, array $payload, bool $signatureValid, ?string $error = null): PaymentNotification
    {
        return PaymentNotification::query()->create([
            'order_id' => $orderId !== null ? mb_substr($orderId, 0, 64) : null,
            'payload' => $payload,
            'signature_valid' => $signatureValid,
            'error' => $error !== null ? mb_substr($error, 0, 255) : null,
        ]);
    }
}
