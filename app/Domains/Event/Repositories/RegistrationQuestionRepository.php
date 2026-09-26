<?php

namespace App\Domains\Event\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Event\Contracts\RegistrationQuestionRepositoryInterface;
use App\Domains\Event\Models\RegistrationQuestion;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<RegistrationQuestion>
 */
class RegistrationQuestionRepository extends BaseRepository implements RegistrationQuestionRepositoryInterface
{
    protected function model(): string
    {
        return RegistrationQuestion::class;
    }

    public function forEvent(string $eventId): Collection
    {
        return $this->query()->where('event_id', $eventId)->orderBy('sort_order')->get();
    }

    public function deleteForEventExcept(string $eventId, array $keepIds): void
    {
        $this->query()->where('event_id', $eventId)->whereNotIn('id', $keepIds)->delete();
    }

    public function answeredIds(string $eventId): array
    {
        /** @var list<string> */
        return $this->query()
            ->where('event_id', $eventId)
            ->whereHas('answers')
            ->pluck('id')
            ->all();
    }
}
