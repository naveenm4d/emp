<?php

namespace App\Domains\Seating\Http\Requests\Dashboard;

use App\Domains\Seating\DTOs\FloorPlanData;
use Illuminate\Foundation\Http\FormRequest;

class SaveFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('event')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $size = ['required', 'integer', 'min:'.StoreVenueElementRequest::MIN_SIZE, 'max:'.StoreVenueElementRequest::MAX_SIZE];

        return [
            'tables' => ['present', 'array', 'max:500'],
            'tables.*.id' => ['required', 'uuid'],
            'tables.*.x' => ['required', 'integer', 'min:0', 'max:'.FloorPlanData::WIDTH],
            'tables.*.y' => ['required', 'integer', 'min:0', 'max:'.FloorPlanData::HEIGHT],
            'elements' => ['present', 'array', 'max:200'],
            'elements.*.id' => ['required', 'uuid'],
            'elements.*.x' => ['required', 'integer', 'min:0', 'max:'.FloorPlanData::WIDTH],
            'elements.*.y' => ['required', 'integer', 'min:0', 'max:'.FloorPlanData::HEIGHT],
            'elements.*.width' => $size,
            'elements.*.height' => $size,
        ];
    }

    public function toData(): FloorPlanData
    {
        return FloorPlanData::fromArray($this->validated());
    }
}
