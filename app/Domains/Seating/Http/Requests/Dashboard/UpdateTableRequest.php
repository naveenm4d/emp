<?php

namespace App\Domains\Seating\Http\Requests\Dashboard;

use App\Domains\Seating\DTOs\TableData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('table')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return StoreTableRequest::tableRules();
    }

    public function toData(): TableData
    {
        return TableData::fromArray($this->validated());
    }
}
