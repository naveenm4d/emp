<?php

namespace App\Domains\Event\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Staff adding extra guests to an event after payment, in blocks; a note is required. */
class UpdateExtraGuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:clients.plan` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'blocks' => ['required', 'integer', 'min:0', 'max:100'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['note.required' => 'Add a note: why the guests were added (e.g. the payment reference).'];
    }

    public function blocks(): int
    {
        return (int) $this->validated('blocks');
    }

    public function note(): string
    {
        return trim((string) $this->validated('note'));
    }
}
