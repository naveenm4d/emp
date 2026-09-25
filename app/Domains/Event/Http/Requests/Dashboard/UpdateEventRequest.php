<?php

namespace App\Domains\Event\Http\Requests\Dashboard;

use App\Domains\Event\DTOs\UpdateEventData;
use Illuminate\Support\Str;

class UpdateEventRequest extends EventRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->event()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // PATCH semantics: validate only the fields that were sent.
        return collect(parent::rules())
            ->map(fn (array $rules) => ['sometimes', ...$rules])
            ->all();
    }

    protected function prepareForValidation(): void
    {
        // Unlike create, never derive the slug from a changed title: the
        // public URL only changes when the slug itself is sent.
        if ($this->has('slug')) {
            $this->merge(['slug' => Str::slug(mb_strtolower(trim((string) $this->input('slug'))))]);
        }

        if ($this->exists('max_capacity')) {
            $this->merge(['max_capacity' => $this->input('max_capacity') ?? 0]);
        }
    }

    public function toData(): UpdateEventData
    {
        return UpdateEventData::fromArray($this->validated());
    }
}
