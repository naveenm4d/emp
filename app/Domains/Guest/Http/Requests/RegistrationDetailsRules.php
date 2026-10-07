<?php

namespace App\Domains\Guest\Http\Requests;

use App\Domains\Event\Enums\DietaryOption;
use App\Domains\Event\Enums\FieldRequirement;
use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\RegistrationQuestion;
use App\Domains\Guest\Models\Guest;
use Illuminate\Validation\Rule;

/**
 * Validation for the details an event asks guests for (Settings tab), shared
 * by public registration and RSVP. Fields the event doesn't ask for are
 * dropped, so they can't be written.
 *
 * Email and phone are validated by the registration form only; guests with
 * a personal link already gave them to the client.
 */
final class RegistrationDetailsRules
{
    /**
     * With a guest (RSVP), plus-ones and children are capped by what the client
     * invited them with, and email / phone follow the settings so guests can
     * correct them. Without one (public registration), the event's settings
     * apply and RegisterGuestRequest validates email / phone.
     *
     * @return array<string, mixed>
     */
    public static function for(Event $event, ?Guest $guest = null): array
    {
        $settings = $event->registrationSettings();
        $allowance = $guest?->partyAllowance($settings) ?? $settings->partyAllowance();

        $rules = [
            'address' => self::text($settings->requirement('address'), 500),
            'company' => self::text($settings->requirement('company'), 255),
            'job_title' => self::text($settings->requirement('job_title'), 255),
            'additional_guests' => $allowance['additional'] > 0
                ? ['nullable', 'integer', 'min:0', 'max:'.$allowance['additional']]
                : ['exclude'],
            'children' => $allowance['children'] > 0
                ? ['nullable', 'integer', 'min:0', 'max:'.$allowance['children']]
                : ['exclude'],
            'dietary_restrictions' => $settings->collectsDietaryRestrictions() ? ['nullable', 'array'] : ['exclude'],
            'dietary_restrictions.*' => $settings->collectsDietaryRestrictions()
                ? ['distinct', Rule::in(array_map(fn (DietaryOption $option) => $option->value, $settings->dietaryOptions))]
                : ['exclude'],
            'dietary_notes' => $settings->dietary && $settings->dietaryNotes ? ['nullable', 'string', 'max:1000'] : ['exclude'],
        ];

        if ($guest !== null) {
            $contact = GuestRules::contact();

            foreach (['email', 'phone'] as $field) {
                $rules[$field] = match ($settings->requirement($field)) {
                    FieldRequirement::Off => ['exclude'],
                    FieldRequirement::Optional => $contact[$field],
                    FieldRequirement::Required => ['required', ...array_values(array_diff($contact[$field], ['nullable']))],
                };
            }
        }

        $questions = $event->registrationQuestions;

        if ($questions->isEmpty()) {
            return [...$rules, 'answers' => ['exclude']];
        }

        $rules['answers'] = ['nullable', 'array:'.$questions->pluck('id')->implode(',')];

        foreach ($questions as $question) {
            foreach (self::answer($question) as $key => $rule) {
                $rules["answers.{$question->id}{$key}"] = $rule;
            }
        }

        return $rules;
    }

    /** @return array<string, string> readable names for the error messages */
    public static function attributes(Event $event): array
    {
        $attributes = [
            'job_title' => 'job title',
            'additional_guests' => 'additional guests',
            'dietary_restrictions' => 'dietary restrictions',
            'dietary_notes' => 'dietary notes',
        ];

        foreach ($event->registrationQuestions as $question) {
            $attributes["answers.{$question->id}"] = $question->label;
            $attributes["answers.{$question->id}.*"] = $question->label;
        }

        return $attributes;
    }

    /** @return list<mixed> */
    private static function text(FieldRequirement $requirement, int $max): array
    {
        return match ($requirement) {
            FieldRequirement::Off => ['exclude'],
            FieldRequirement::Optional => ['nullable', 'string', "max:{$max}"],
            FieldRequirement::Required => ['required', 'string', "max:{$max}"],
        };
    }

    /** @return array<string, list<mixed>> rules keyed by suffix ('' for the answer itself, '.*' for list items) */
    private static function answer(RegistrationQuestion $question): array
    {
        $presence = $question->required ? 'required' : 'nullable';
        $options = $question->options ?? [];

        return match ($question->type) {
            QuestionType::Text => ['' => [$presence, 'string', 'max:500']],
            QuestionType::Textarea => ['' => [$presence, 'string', 'max:2000']],
            QuestionType::Number => ['' => [$presence, 'numeric', 'between:-1000000000,1000000000']],
            QuestionType::Select, QuestionType::Radio => ['' => [$presence, 'string', Rule::in($options)]],
            QuestionType::MultiSelect => [
                '' => [$presence, 'array'],
                '.*' => ['string', 'distinct', Rule::in($options)],
            ],
            // A required yes / no question must be ticked (e.g. agreeing to terms).
            QuestionType::Checkbox => ['' => $question->required ? ['accepted'] : ['nullable', 'boolean']],
        };
    }
}
