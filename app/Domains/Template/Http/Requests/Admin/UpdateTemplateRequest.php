<?php

namespace App\Domains\Template\Http\Requests\Admin;

use App\Domains\Template\DTOs\UpdateTemplateData;
use App\Domains\Template\Enums\TemplateCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:templates.manage` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::enum(TemplateCategory::class)],
            'tags' => ['array', 'max:20'],
            'tags.*' => ['string', 'max:32'],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'display_price' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function toData(): UpdateTemplateData
    {
        return UpdateTemplateData::fromArray($this->validated());
    }
}
