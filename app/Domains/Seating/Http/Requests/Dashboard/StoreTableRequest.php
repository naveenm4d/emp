<?php

namespace App\Domains\Seating\Http\Requests\Dashboard;

use App\Domains\Seating\DTOs\TableData;
use App\Domains\Seating\Enums\TableShape;
use App\Domains\Seating\Models\EventTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('event')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::tableRules();
    }

    /** @return array<string, list<mixed>> */
    public static function tableRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'seat_count' => ['required', 'integer', 'min:1', 'max:'.EventTable::MAX_SEATS],
            'shape' => ['required', Rule::enum(TableShape::class)],
        ];
    }

    public function toData(): TableData
    {
        return TableData::fromArray($this->validated());
    }
}
