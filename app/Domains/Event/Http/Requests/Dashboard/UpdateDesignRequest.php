<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Event\Models\Event;
use App\Domains\Template\DTOs\EditableSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Changes from the invitation editor. What may be changed, and how, comes
 * from the "editable" schema of the event's template version.
 */
class UpdateDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->event()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schema = $this->event()->templateVersion->editable();

        $rules = [
            'texts' => $this->keysRule($schema->texts),
            'colors' => $this->keysRule($schema->colors),
            'sections' => $this->keysRule($schema->sections),
        ];

        foreach ($schema->texts as $key => $text) {
            $rules["texts.{$key}"] = ['nullable', 'string', "max:{$text['max']}"];
        }

        foreach (array_keys($schema->colors) as $key) {
            $rules["colors.{$key}"] = ['sometimes', 'string', 'regex:'.EditableSchema::COLOR_PATTERN];
        }

        foreach (array_keys($schema->sections) as $key) {
            $rules["sections.{$key}"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'colors.*.regex' => 'Use a colour like #b08d57.',
            '*.array' => 'This template has no such setting.',
            '*.prohibited' => 'This template has no such setting.',
        ];
    }

    /** @return array{texts?: array<string, string>, colors?: array<string, string>, sections?: array<string, bool>} */
    public function changes(): array
    {
        $validated = $this->validated();

        return array_filter([
            // Empty inputs arrive as null (ConvertEmptyStringsToNull); an empty text is allowed.
            'texts' => array_map(fn (?string $text) => (string) $text, $validated['texts'] ?? []),
            'colors' => $validated['colors'] ?? [],
            'sections' => array_map(fn (mixed $on) => (bool) $on, $validated['sections'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $declared
     * @return list<string>
     */
    private function keysRule(array $declared): array
    {
        return $declared === [] ? ['prohibited'] : ['sometimes', 'array:'.implode(',', array_keys($declared))];
    }

    private function event(): Event
    {
        /** @var Event */
        return $this->route('event');
    }
}
