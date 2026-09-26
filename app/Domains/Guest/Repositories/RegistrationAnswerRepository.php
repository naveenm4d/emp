<?php

namespace App\Domains\Guest\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Guest\Contracts\RegistrationAnswerRepositoryInterface;
use App\Domains\Guest\Models\RegistrationAnswer;

/**
 * @extends BaseRepository<RegistrationAnswer>
 */
class RegistrationAnswerRepository extends BaseRepository implements RegistrationAnswerRepositoryInterface
{
    protected function model(): string
    {
        return RegistrationAnswer::class;
    }

    public function sync(string $guestId, array $answers): void
    {
        foreach ($answers as $questionId => $value) {
            if ($value === null || $value === []) {
                $this->query()->where('guest_id', $guestId)->where('question_id', $questionId)->delete();

                continue;
            }

            $this->query()->updateOrCreate(
                ['guest_id' => $guestId, 'question_id' => $questionId],
                ['value' => $value],
            );
        }
    }
}
