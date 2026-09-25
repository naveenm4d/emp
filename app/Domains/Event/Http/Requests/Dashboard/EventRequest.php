<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Template\Contracts\TemplateQueryServiceInterface;
use App\Domains\Template\Exceptions\TemplateNotAvailableException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and updating an event.
 */
abstract class EventRequest extends FormRequest
{
    /** The event being updated, or null when creating. */
    protected function event(): ?Event
    {
        $event = $this->route('event');

        return $event instanceof Event ? $event : null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash:ascii',
                Rule::unique(Event::class, 'slug')->ignore($this->event()?->id),
            ],
            'template_id' => ['required', 'uuid', $this->selectableTemplate(...)],
            'description' => ['nullable', 'string', 'max:10000'],
            'max_capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'require_approval' => ['boolean'],
            'event_type' => ['nullable', 'string', 'max:64'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
        ];
    }

    /** Every event must have a template the client is allowed to use. */
    protected function selectableTemplate(string $attribute, mixed $value, Closure $fail): void
    {
        $client = $this->user('client');

        try {
            app(TemplateQueryServiceInterface::class)->findSelectable((string) $value, $client instanceof Client ? $client : null);
        } catch (TemplateNotAvailableException) {
            $fail('The selected template is not available.');
        }
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->filled('slug') ? $this->input('slug') : $this->input('title');

        $this->merge([
            'slug' => Str::slug(mb_strtolower(trim((string) $slug))),
            'max_capacity' => $this->input('max_capacity') ?? 0,
        ]);
    }
}
