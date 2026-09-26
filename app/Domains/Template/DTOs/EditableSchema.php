<?php

namespace App\Domains\Template\DTOs;

use App\Domains\Template\Support\PlaceholderFormat;
use Illuminate\Support\HtmlString;

/**
 * What a template version lets clients change, as declared in the "editable"
 * block of its template.json (the way an Elementor widget declares its
 * controls). The editor builds its controls from this, and rendering fills
 * {{ text.* }}, {{#if section.* }} and var(--emp-color-*) from it.
 *
 * A client's customisations are overrides only; keys the schema does not
 * declare are ignored, so switching or upgrading a template never breaks.
 */
final readonly class EditableSchema
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';

    public const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * @param  array<string, array{label: string, default: string, multiline: bool, max: int}>  $texts
     * @param  array<string, array{label: string, default: string}>  $colors
     * @param  array<string, array{label: string, default: bool}>  $sections
     */
    public function __construct(
        public array $texts = [],
        public array $colors = [],
        public array $sections = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            texts: array_map(fn (array $text) => [
                'label' => (string) $text['label'],
                'default' => (string) ($text['default'] ?? ''),
                'multiline' => (bool) ($text['multiline'] ?? false),
                'max' => (int) ($text['max'] ?? 300),
            ], $data['texts'] ?? []),
            colors: array_map(fn (array $color) => [
                'label' => (string) $color['label'],
                'default' => strtolower((string) $color['default']),
            ], $data['colors'] ?? []),
            sections: array_map(fn (array $section) => [
                'label' => (string) $section['label'],
                'default' => (bool) ($section['default'] ?? true),
            ], $data['sections'] ?? []),
        );
    }

    /** @return array{texts: array<string, array{label: string, default: string, multiline: bool, max: int}>, colors: array<string, array{label: string, default: string}>, sections: array<string, array{label: string, default: bool}>} */
    public function toArray(): array
    {
        return ['texts' => $this->texts, 'colors' => $this->colors, 'sections' => $this->sections];
    }

    public function isEmpty(): bool
    {
        return $this->texts === [] && $this->colors === [] && $this->sections === [];
    }

    /**
     * Defaults with the client's valid overrides applied.
     *
     * @param  array<string, mixed>  $customizations
     * @return array{texts: array<string, string>, colors: array<string, string>, sections: array<string, bool>}
     */
    public function resolve(array $customizations = []): array
    {
        $resolved = ['texts' => [], 'colors' => [], 'sections' => []];

        foreach ($this->texts as $key => $text) {
            $value = $customizations['texts'][$key] ?? null;
            $resolved['texts'][$key] = is_string($value) ? mb_substr($value, 0, $text['max']) : $text['default'];
        }

        foreach ($this->colors as $key => $color) {
            $value = $customizations['colors'][$key] ?? null;
            $resolved['colors'][$key] = is_string($value) && preg_match(self::COLOR_PATTERN, $value) ? strtolower($value) : $color['default'];
        }

        foreach ($this->sections as $key => $section) {
            $value = $customizations['sections'][$key] ?? null;
            $resolved['sections'][$key] = is_bool($value) ? $value : $section['default'];
        }

        return $resolved;
    }

    /**
     * Placeholder values: text.* (escaped, line breaks kept) and section.* ('1' or null).
     *
     * @param  array<string, mixed>  $customizations
     * @return array<string, HtmlString|string|null>
     */
    public function values(array $customizations = []): array
    {
        $resolved = $this->resolve($customizations);
        $values = [];

        foreach ($resolved['texts'] as $key => $text) {
            $values["text.{$key}"] = $this->texts[$key]['multiline'] ? PlaceholderFormat::multiline($text) : $text;
        }

        foreach ($resolved['sections'] as $key => $on) {
            $values["section.{$key}"] = $on ? '1' : null;
        }

        return $values;
    }

    /**
     * CSS custom properties for the colours, prepended to the template's styles.
     *
     * @param  array<string, mixed>  $customizations
     */
    public function colorStyles(array $customizations = []): string
    {
        $colors = $this->resolve($customizations)['colors'];

        if ($colors === []) {
            return '';
        }

        $properties = implode("\n", array_map(
            fn (string $key, string $value) => "    --emp-color-{$key}: {$value};",
            array_keys($colors),
            $colors,
        ));

        return ":host {\n{$properties}\n}\n";
    }
}
