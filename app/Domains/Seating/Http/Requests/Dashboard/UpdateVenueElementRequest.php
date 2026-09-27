<?php

namespace App\Domains\Seating\Http\Requests\Dashboard;

use App\Domains\Seating\DTOs\VenueElementData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVenueElementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('venueElement')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return StoreVenueElementRequest::elementRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['label.required_if' => 'Give the custom element a name.'];
    }

    public function toData(): VenueElementData
    {
        return VenueElementData::fromArray($this->validated());
    }
}
