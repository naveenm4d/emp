<?php

namespace App\Domains\Notification\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Notification\Contracts\NotificationRepositoryInterface;
use App\Domains\Notification\Enums\NotificationKind;
use App\Domains\Notification\Enums\NotificationStatus;
use App\Domains\Notification\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<Notification>
 */
class NotificationRepository extends BaseRepository implements NotificationRepositoryInterface
{
    protected function model(): string
    {
        return Notification::class;
    }

    public function findByProviderMessageId(string $messageId): ?Notification
    {
        return $this->query()->where('provider_message_id', $messageId)->first();
    }

    public function paginateForEvent(string $eventId, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->where('event_id', $eventId)
            ->with('guest:id,name')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countForGuest(string $guestId, NotificationKind $kind): int
    {
        return $this->query()
            ->where('guest_id', $guestId)
            ->where('kind', $kind)
            ->where('status', '!=', NotificationStatus::Failed)
            ->count();
    }

    public function forGuest(string $guestId): Collection
    {
        return $this->query()
            ->where('guest_id', $guestId)
            ->with('deliveries')
            ->latest()
            ->get();
    }

    public function paginateByStatus(NotificationStatus $status, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->where('status', $status)
            ->with(['guest:id,name', 'event:id,title,client_id', 'event.client:id,name'])
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countByStatus(NotificationStatus $status): int
    {
        return $this->query()->where('status', $status)->count();
    }

    public function stalePendingIds(int $olderThanMinutes, int $limit = 500): Collection
    {
        return $this->query()
            ->where('status', NotificationStatus::Pending)
            ->where('updated_at', '<', now()->subMinutes($olderThanMinutes))
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id');
    }

    public function recordDelivery(Notification $notification, array $attributes): void
    {
        $notification->deliveries()->create($attributes);
    }
}
