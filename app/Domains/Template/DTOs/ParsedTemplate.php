<?php

namespace App\Domains\Template\DTOs;

/**
 * What a template's code uses, derived by the PlaceholderParser.
 * Stored on template_versions.placeholders so it is parsed once, at import.
 */
final readonly class ParsedTemplate
{
    /**
     * @param  list<MediaSlot>  $slots
     * @param  list<string>  $fields  event.* / guest.* names used
     * @param  list<string>  $assets  asset paths used (relative to assets/)
     */
    public function __construct(
        public array $slots,
        public array $fields,
        public array $assets,
        public bool $hasRsvp,
    ) {}

    /** @param array{slots?: list<array{key: string, type?: string, required?: bool, label?: string|null}>, fields?: list<string>, assets?: list<string>, has_rsvp?: bool} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            slots: array_map(MediaSlot::fromArray(...), $data['slots'] ?? []),
            fields: $data['fields'] ?? [],
            assets: $data['assets'] ?? [],
            hasRsvp: (bool) ($data['has_rsvp'] ?? false),
        );
    }

    /** @return array{slots: list<array{key: string, type: string, required: bool}>, fields: list<string>, assets: list<string>, has_rsvp: bool} */
    public function toArray(): array
    {
        return [
            'slots' => array_map(fn (MediaSlot $slot) => [
                'key' => $slot->key,
                'type' => $slot->type->value,
                'required' => $slot->required,
            ], $this->slots),
            'fields' => $this->fields,
            'assets' => $this->assets,
            'has_rsvp' => $this->hasRsvp,
        ];
    }
}
