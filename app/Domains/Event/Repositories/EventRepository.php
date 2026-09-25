<?php

namespace App\Domains\Event\Repositories;

use App\Core\Exceptions\NotFoundException;
use App\Core\Repositories\BaseRepository;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\DTOs\EventFilters;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Event>
 */
class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    protected function model(): string
    {
        return Event::class;
    }

    public function findBySlug(string $slug): ?Event
    {
        return $this->query()->where('slug', mb_strtolower(trim($slug)))->first();
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        return $this->query()
            ->where('slug', $slug)
            ->when($exceptId, fn (Builder $query, string $id) => $query->whereKeyNot($id))
            ->exists();
    }

    public function findAndLock(string $id): Event
    {
        return $this->query()->lockForUpdate()->find($id)
            ?? throw new NotFoundException('Event not found.');
    }

    public function paginateForClient(string $clientId, EventFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->where('client_id', $clientId)
            ->withCount('guests')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateAll(EventFilters $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with('client:id,name,email')
            ->withCount('guests')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countByState(?string $clientId = null): array
    {
        $counts = $this->query()
            ->when($clientId, fn (Builder $query, string $id) => $query->where('client_id', $id))
            ->toBase()
            ->selectRaw('state, count(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        return collect(EventState::values())
            ->mapWithKeys(fn (string $state) => [$state => (int) ($counts[$state] ?? 0)])
            ->all();
    }

    public function recordRender(Event $event, string $path, string $hash): void
    {
        $attributes = ['rendered_path' => $path, 'rendered_hash' => $hash, 'rendered_at' => now()];

        $this->query()->whereKey($event->id)->toBase()->update($attributes);

        $event->forceFill($attributes)->syncOriginalAttributes(array_keys($attributes));
    }

    /** @return Builder<Event> */
    private function filtered(EventFilters $filters): Builder
    {
        return $this->query()
            ->when($filters->state, fn (Builder $query, EventState $state) => $query->where('state', $state))
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('title', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('slug', "%{$search}%", caseSensitive: false),
            ));
    }
}
