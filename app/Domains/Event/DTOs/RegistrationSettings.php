<?php

namespace App\Domains\Event\DTOs;

use App\Domains\Event\Enums\DietaryOption;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\FieldRequirement;
use App\Domains\Event\Enums\RegistrationType;

/**
 * What an event asks guests for when they register (public link) or RSVP
 * (personal link), besides their name and attendance. Stored as jsonb in
 * events.event_registration_settings; custom questions live in
 * registration_questions.
 *
 * Email and phone identify public registrants. On a personal link they are
 * filled in with what the client entered, so guests can correct them.
 */
final readonly class RegistrationSettings
{
    /** @var list<string> built-in contact fields, in form order */
    public const array CONTACT_FIELDS = ['email', 'phone', 'address', 'company', 'job_title'];

    public const int MAX_ADDITIONAL_GUESTS = 20;

    /**
     * @param  array<string, FieldRequirement>  $contact  keyed by CONTACT_FIELDS
     * @param  list<DietaryOption>  $dietaryOptions
     */
    public function __construct(
        public array $contact,
        public bool $allowMaybe = false,
        public bool $plusOnes = false,
        public int $maxAdditionalGuests = 1,
        public bool $children = false,
        public bool $dietary = false,
        public array $dietaryOptions = [],
        public bool $dietaryNotes = false,
        public bool $responsesEditable = false,
    ) {}

    /**
     * Sensible starting point for a new event, based on the kind of occasion.
     * Public registration asks for an email or phone; guest-list events
     * already have them.
     */
    public static function defaultsFor(?EventType $type, ?RegistrationType $registration = null): self
    {
        $asksContact = $registration?->hasPublicRegistration() ?? true;

        $contact = array_fill_keys(self::CONTACT_FIELDS, FieldRequirement::Off);
        $contact['email'] = $asksContact ? FieldRequirement::Optional : FieldRequirement::Off;
        $contact['phone'] = $asksContact ? FieldRequirement::Optional : FieldRequirement::Off;

        return match ($type) {
            EventType::Wedding, EventType::Engagement, EventType::Birthday, EventType::BabyShower,
            EventType::Anniversary, EventType::Party => new self(contact: $contact, plusOnes: true),
            EventType::Corporate, EventType::Conference => new self(
                contact: [
                    ...$contact,
                    'email' => $asksContact ? FieldRequirement::Required : FieldRequirement::Off,
                    'company' => FieldRequirement::Optional,
                    'job_title' => FieldRequirement::Optional,
                ],
                dietary: true,
                dietaryOptions: DietaryOption::cases(),
                dietaryNotes: true,
            ),
            default => new self(contact: $contact),
        };
    }

    /**
     * Reads the stored settings; anything missing falls back to the defaults.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?self $defaults = null): self
    {
        $defaults ??= self::defaultsFor(null);

        $contact = [];

        foreach (self::CONTACT_FIELDS as $field) {
            $contact[$field] = FieldRequirement::tryFrom((string) ($data['contact'][$field] ?? '')) ?? $defaults->contact[$field];
        }

        $dietaryOptions = isset($data['dietary']['options']) && is_array($data['dietary']['options'])
            ? array_values(array_filter(array_map(
                fn (mixed $option) => DietaryOption::tryFrom((string) $option),
                $data['dietary']['options'],
            )))
            : $defaults->dietaryOptions;

        return new self(
            contact: $contact,
            allowMaybe: (bool) ($data['attendance']['allow_maybe'] ?? $defaults->allowMaybe),
            plusOnes: (bool) ($data['party']['plus_ones'] ?? $defaults->plusOnes),
            maxAdditionalGuests: max(0, min(self::MAX_ADDITIONAL_GUESTS, (int) ($data['party']['max_additional_guests'] ?? $defaults->maxAdditionalGuests))),
            children: (bool) ($data['party']['children'] ?? $defaults->children),
            dietary: (bool) ($data['dietary']['enabled'] ?? $defaults->dietary),
            dietaryOptions: $dietaryOptions,
            dietaryNotes: (bool) ($data['dietary']['notes'] ?? $defaults->dietaryNotes),
            responsesEditable: (bool) ($data['responses']['editable'] ?? $defaults->responsesEditable),
        );
    }

    /**
     * @return array{
     *     contact: array<string, string>,
     *     attendance: array{allow_maybe: bool},
     *     party: array{plus_ones: bool, max_additional_guests: int, children: bool},
     *     dietary: array{enabled: bool, options: list<string>, notes: bool},
     *     responses: array{editable: bool},
     * }
     */
    public function toArray(): array
    {
        return [
            'contact' => array_map(fn (FieldRequirement $requirement) => $requirement->value, $this->contact),
            'attendance' => ['allow_maybe' => $this->allowMaybe],
            'party' => [
                'plus_ones' => $this->plusOnes,
                'max_additional_guests' => $this->maxAdditionalGuests,
                'children' => $this->children,
            ],
            'dietary' => [
                'enabled' => $this->dietary,
                'options' => array_map(fn (DietaryOption $option) => $option->value, $this->dietaryOptions),
                'notes' => $this->dietaryNotes,
            ],
            'responses' => ['editable' => $this->responsesEditable],
        ];
    }

    public function requirement(string $field): FieldRequirement
    {
        return $this->contact[$field] ?? FieldRequirement::Off;
    }

    /** Dietary restrictions are asked and there is something to pick from. */
    public function collectsDietaryRestrictions(): bool
    {
        return $this->dietary && $this->dietaryOptions !== [];
    }
}
