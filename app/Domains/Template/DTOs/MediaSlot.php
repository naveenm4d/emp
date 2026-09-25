<?php

namespace App\Domains\Template\DTOs;

use App\Domains\Template\Enums\MediaType;

/**
 * A media placeholder declared by template code, e.g. {{ img_1 }}.
 */
final readonly class MediaSlot
{
    public function __construct(
        public string $key,
        public MediaType $type,
        public bool $required,
        public ?string $label = null,
    ) {}

    public static function typeOf(string $key): MediaType
    {
        return match (true) {
            $key === 'bg_music' => MediaType::Audio,
            str_starts_with($key, 'video_') => MediaType::Video,
            default => MediaType::Image,
        };
    }

    /** @param array{key: string, type?: string, required?: bool, label?: string|null} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            type: isset($data['type']) ? MediaType::from($data['type']) : self::typeOf($data['key']),
            required: (bool) ($data['required'] ?? false),
            label: $data['label'] ?? null,
        );
    }

    public function withLabel(?string $label): self
    {
        return new self($this->key, $this->type, $this->required, $label);
    }

    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        if ($this->key === 'bg_music') {
            return 'Background music';
        }

        [, $number] = explode('_', $this->key);

        return ucfirst($this->type === MediaType::Image ? 'image' : $this->type->value).' '.$number;
    }

    /** @return array{mimetypes: list<string>, max_kb: int} */
    public function limits(): array
    {
        /** @var array{mimetypes: list<string>, max_kb: int} */
        return config("emp.media.{$this->type->value}");
    }

    /** @return list<string> validation rules for an uploaded file */
    public function uploadRules(): array
    {
        $limits = $this->limits();

        return ['required', 'file', 'mimetypes:'.implode(',', $limits['mimetypes']), 'max:'.$limits['max_kb']];
    }

    /** @return array{key: string, type: string, required: bool, label: string} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'required' => $this->required,
            'label' => $this->displayLabel(),
        ];
    }
}
