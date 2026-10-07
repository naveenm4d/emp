<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Event\DTOs\RegistrationSettings;
use App\Domains\Event\DTOs\RegistrationSettingsData;
use App\Domains\Event\Enums\DietaryOption;
use App\Domains\Event\Enums\FieldRequirement;
use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The event's registration / RSVP form, saved from the Settings tab.
 */
class UpdateRegistrationSettingsRequest extends FormRequest
{
    public const int MAX_QUESTIONS = 30;

    public const int MAX_OPTIONS = 30;

    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->event()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $optionTypes = array_map(
            fn (QuestionType $type) => $type->value,
            array_filter(QuestionType::cases(), fn (QuestionType $type) => $type->hasOptions()),
        );

        $rules = [
            'settings' => ['required', 'array'],
            'settings.contact' => ['required', 'array'],
            'settings.attendance.allow_maybe' => ['required', 'boolean'],
            'settings.party.plus_ones' => ['required', 'boolean'],
            'settings.party.max_additional_guests' => ['required', 'integer', 'min:1', 'max:'.RegistrationSettings::MAX_ADDITIONAL_GUESTS],
            'settings.party.children' => ['required', 'boolean'],
            'settings.party.max_children' => ['required', 'integer', 'min:1', 'max:'.RegistrationSettings::MAX_CHILDREN],
            'settings.dietary.enabled' => ['required', 'boolean'],
            'settings.dietary.options' => ['present', 'array', Rule::requiredIf(fn () => $this->boolean('settings.dietary.enabled'))],
            'settings.dietary.options.*' => ['distinct', Rule::enum(DietaryOption::class)],
            'settings.dietary.notes' => ['required', 'boolean'],
            'settings.responses.editable' => ['required', 'boolean'],
            'responses_lock_at' => ['nullable', 'date'],
            'questions' => ['present', 'array', 'max:'.self::MAX_QUESTIONS],
            'questions.*.id' => ['nullable', 'uuid', 'distinct'],
            'questions.*.label' => ['required', 'string', 'max:255'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.options' => ['nullable', 'array', 'required_if:questions.*.type,'.implode(',', $optionTypes), 'max:'.self::MAX_OPTIONS],
            'questions.*.options.*' => ['required', 'string', 'max:100'],
        ];

        foreach (RegistrationSettings::CONTACT_FIELDS as $field) {
            $rules["settings.contact.{$field}"] = ['required', Rule::enum(FieldRequirement::class)];
        }

        return $rules;
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // Public registrants must leave a way to reach them (duplicates and WhatsApp use it).
                $contact = $this->input('settings.contact');

                if ($this->event()->registration_type->hasPublicRegistration()
                    && $contact['email'] === FieldRequirement::Off->value
                    && $contact['phone'] === FieldRequirement::Off->value) {
                    $validator->errors()->add('settings.contact.email', 'Ask for an email or a phone number, so you can reach people who register.');
                }

                foreach ($this->input('questions', []) as $index => $question) {
                    $type = QuestionType::from($question['type']);
                    $options = array_map(fn (mixed $option) => mb_strtolower(trim((string) $option)), $question['options'] ?? []);

                    if ($type->hasOptions() && count(array_unique($options)) < 2) {
                        $validator->errors()->add("questions.{$index}.options", 'Add at least two different choices.');
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'questions.*.label.required' => 'Write the question.',
            'questions.*.options.required_if' => 'Add the choices guests pick from.',
            'questions.*.options.*.required' => 'Choices cannot be empty.',
            'questions.max' => 'An event can have up to '.self::MAX_QUESTIONS.' questions.',
        ];
    }

    public function toData(): RegistrationSettingsData
    {
        return RegistrationSettingsData::fromArray($this->validated());
    }

    private function event(): Event
    {
        /** @var Event */
        return $this->route('event');
    }
}
