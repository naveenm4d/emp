<?php

namespace App\Domains\Guest\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Models\Event;

/**
 * The details a guest gave when registering or RSVPing: the built-in fields
 * the event asks for, and answers to its custom questions.
 */
final readonly class RegistrationDetailsData extends DataTransferObject
{
    /** @var list<string> guest columns the form can fill (email / phone only on RSVP; registration keeps them in GuestData) */
    public const array PROFILE_FIELDS = [
        'email', 'phone', 'address', 'company', 'job_title', 'additional_guests', 'children', 'dietary_restrictions', 'dietary_notes',
    ];

    /**
     * @param  array<string, mixed>  $profile  guest columns to write; only fields the event asks for
     * @param  array<string, mixed>  $answers  question id => answer; null clears the answer
     */
    public function __construct(
        public array $profile = [],
        public array $answers = [],
    ) {}

    /**
     * Built from input validated with RegistrationDetailsRules, which drops
     * the fields the event doesn't ask for.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, Event $event, bool $withContact = false): self
    {
        $fields = $withContact ? self::PROFILE_FIELDS : array_diff(self::PROFILE_FIELDS, ['email', 'phone']);
        $profile = array_intersect_key($validated, array_flip($fields));

        if (array_key_exists('email', $profile)) {
            $profile['email'] = GuestContact::email($profile['email']);
        }

        if (array_key_exists('phone', $profile)) {
            $profile['phone'] = GuestContact::phone($profile['phone']);
        }

        foreach (['additional_guests', 'children'] as $count) {
            if (array_key_exists($count, $profile)) {
                $profile[$count] = (int) $profile[$count];
            }
        }

        if (array_key_exists('dietary_restrictions', $profile)) {
            $profile['dietary_restrictions'] = array_values($profile['dietary_restrictions'] ?? []) ?: null;
        }

        foreach (['address', 'company', 'job_title', 'dietary_notes'] as $text) {
            if (array_key_exists($text, $profile)) {
                $profile[$text] = $profile[$text] === null ? null : (trim((string) $profile[$text]) ?: null);
            }
        }

        $answers = [];

        foreach ($event->registrationQuestions as $question) {
            $value = $validated['answers'][$question->id] ?? null;

            $answers[$question->id] = match ($question->type) {
                QuestionType::Number => $value === null ? null : $value + 0,
                QuestionType::Checkbox => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN),
                QuestionType::MultiSelect => $value ? array_values($value) : null,
                default => $value === null ? null : (trim((string) $value) ?: null),
            };
        }

        return new self($profile, $answers);
    }
}
