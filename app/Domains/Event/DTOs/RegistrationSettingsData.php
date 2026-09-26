<?php

namespace App\Domains\Event\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Event\Enums\QuestionType;
use Carbon\CarbonImmutable;

/**
 * The client's registration / RSVP form: built-in fields, when answers
 * become final, and the custom questions in form order.
 */
final readonly class RegistrationSettingsData extends DataTransferObject
{
    /**
     * @param  list<array{id: string|null, label: string, type: QuestionType, required: bool, options: list<string>|null}>  $questions
     */
    public function __construct(
        public RegistrationSettings $settings,
        public ?CarbonImmutable $responsesLockAt,
        public array $questions,
    ) {}

    /** @param array<string, mixed> $data validated input */
    public static function fromArray(array $data): self
    {
        // validated() can rebuild the list out of order (keys are added rule by rule).
        $questions = $data['questions'] ?? [];
        ksort($questions);

        return new self(
            settings: RegistrationSettings::fromArray($data['settings']),
            responsesLockAt: isset($data['responses_lock_at']) ? CarbonImmutable::parse($data['responses_lock_at']) : null,
            questions: array_map(function (array $question) {
                $type = QuestionType::from($question['type']);

                return [
                    'id' => $question['id'] ?? null,
                    'label' => trim((string) $question['label']),
                    'type' => $type,
                    'required' => (bool) ($question['required'] ?? false),
                    'options' => $type->hasOptions()
                        ? array_values(array_unique(array_map(fn (mixed $option) => trim((string) $option), $question['options'] ?? [])))
                        : null,
                ];
            }, array_values($questions)),
        );
    }
}
