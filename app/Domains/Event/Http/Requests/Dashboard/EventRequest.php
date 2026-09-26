<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\RegistrationType;
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

    /** The client the event belongs to; decides which templates are selectable. */
    protected function client(): ?Client
    {
        $client = $this->user('client');

        return $client instanceof Client ? $client : null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash:ascii',
                // Not unique: the link's code identifies the event, the slug is only a label.
                Rule::notIn(Event::RESERVED_SLUGS),
            ],
            'template_id' => ['required', 'uuid', $this->selectableTemplate(...)],
            'description' => ['nullable', 'string', 'max:10000'],
            'max_capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'registration_type' => ['required', Rule::enum(RegistrationType::class)],
            'event_type' => ['nullable', Rule::enum(EventType::class)],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url', 'max:2048'],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'invitation_message' => ['nullable', 'string', 'max:1000'],
            'reminder_message' => ['nullable', 'string', 'max:1000'],
            'auto_reminders' => ['boolean'],
            'remind_after_days' => ['integer', 'min:1', 'max:60'],
            'remind_before_days' => ['integer', 'min:0', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.not_in' => 'This URL is reserved; choose another slug.',
            'start_time.required_with' => 'Set a start time when the event has an end time.',
            'end_time.after' => 'The end time must be after the start time.',
        ];
    }

    /** Every event must have a template the client is allowed to use. */
    protected function selectableTemplate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(TemplateQueryServiceInterface::class)->findSelectable((string) $value, $this->client());
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
            // Without a choice a new event is guest-list only (UpdateEventRequest has its own prepare).
            'registration_type' => $this->input('registration_type') ?? RegistrationType::GuestListOnly->value,
        ]);
    }
}
