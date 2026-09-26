<?php

namespace App\Domains\Event\Services;

use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\Contracts\RegistrationQuestionRepositoryInterface;
use App\Domains\Event\Contracts\RegistrationSettingsServiceInterface;
use App\Domains\Event\DTOs\RegistrationSettingsData;
use App\Domains\Event\Exceptions\EventNotEditableException;
use App\Domains\Event\Exceptions\QuestionHasAnswersException;
use App\Domains\Event\Models\Event;

class RegistrationSettingsService extends BaseService implements RegistrationSettingsServiceInterface
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly RegistrationQuestionRepositoryInterface $questions,
    ) {}

    public function update(Event $event, RegistrationSettingsData $data): Event
    {
        if (! $event->state->isEditable()) {
            throw new EventNotEditableException;
        }

        return $this->transaction(function () use ($event, $data) {
            /** @var Event $event */
            $event = $this->events->update($event, [
                'event_registration_settings' => $data->settings->toArray(),
                'responses_lock_at' => $data->responsesLockAt,
            ]);

            $existing = $this->questions->forEvent($event->id)->keyBy('id');
            $answered = array_flip($this->questions->answeredIds($event->id));
            $keep = array_values(array_filter(array_column($data->questions, 'id')));

            $this->questions->deleteForEventExcept($event->id, $keep);

            foreach ($data->questions as $position => $question) {
                $attributes = [
                    'label' => $question['label'],
                    'type' => $question['type'],
                    'required' => $question['required'],
                    'options' => $question['options'],
                    'sort_order' => $position,
                ];

                if ($question['id'] === null) {
                    $this->questions->create([...$attributes, 'event_id' => $event->id]);

                    continue;
                }

                $current = $existing->get($question['id'])
                    ?? throw new NotFoundException('Question not found.');

                // Existing answers only make sense for the type they were given in.
                if ($current->type !== $question['type'] && isset($answered[$current->id])) {
                    throw new QuestionHasAnswersException;
                }

                $this->questions->update($current, $attributes);
            }

            return $event->load('registrationQuestions');
        });
    }
}
