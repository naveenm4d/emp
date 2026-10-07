<?php

namespace App\Domains\Seating\Http\Requests\Dashboard;

use App\Domains\Seating\DTOs\FloorPlanData;
use App\Domains\Seating\DTOs\VenueElementData;
use App\Domains\Seating\Enums\VenueElementKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueElementRequest extends FormRequest
{
    /** Smallest and largest side of an element, in canvas units. */
    public const int MIN_SIZE = 40;

    public const int MAX_SIZE = 800;

    public function authorize(): bool
    {
        return $this->user('client')?->can('manageSeating', $this->route('event')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::elementRules();
    }

    /** @return array<string, list<mixed>> */
    public static function elementRules(): array
    {
        return [
            'kind' => ['required', Rule::enum(VenueElementKind::class)],
            'label' => ['nullable', 'string', 'max:40', 'required_if:kind,'.VenueElementKind::Custom->value],
            'x' => ['required', 'integer', 'min:0', 'max:'.FloorPlanData::WIDTH],
            'y' => ['required', 'integer', 'min:0', 'max:'.FloorPlanData::HEIGHT],
            'width' => ['required', 'integer', 'min:'.self::MIN_SIZE, 'max:'.self::MAX_SIZE],
            'height' => ['required', 'integer', 'min:'.self::MIN_SIZE, 'max:'.self::MAX_SIZE],
        ];
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
