<?php

namespace App\Domains\Event\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Event\Models\RegistrationQuestion;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<RegistrationQuestion>
 */
interface RegistrationQuestionRepositoryInterface extends RepositoryInterface
{
    /** @return Collection<int, RegistrationQuestion> in form order */
    public function forEvent(string $eventId): Collection;

    /**
     * Deletes the event's questions not listed (their answers go with them).
     *
     * @param  list<string>  $keepIds
     */
    public function deleteForEventExcept(string $eventId, array $keepIds): void;

    /** @return list<string> ids of the event's questions that have at least one answer */
    public function answeredIds(string $eventId): array;
}
