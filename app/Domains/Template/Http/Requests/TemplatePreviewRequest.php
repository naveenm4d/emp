<?php

namespace App\Domains\Template\Http\Requests;

use App\Domains\Event\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Event details typed into the form so far, shown in a template preview.
 * Every field is optional and an invalid one is simply left out (the preview
 * shows sample text instead), so a half-filled form never breaks the preview.
 */
class TemplatePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the route decides who may preview which template
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return array<string, string> the valid, non-empty details */
    public function details(): array
    {
        $rules = [
            'title' => ['string', 'max:255'],
            'description' => ['string', 'max:10000'],
            'event_type' => [Rule::enum(EventType::class)],
            'event_date' => ['date_format:Y-m-d'],
            'start_time' => ['date_format:H:i'],
            'end_time' => ['date_format:H:i'],
            'location_name' => ['string', 'max:255'],
            'location_address' => ['string', 'max:1000'],
        ];

        return collect($rules)
            ->filter(fn (array $fieldRules, string $field) => $this->filled($field)
                && Validator::make([$field => $this->input($field)], [$field => $fieldRules])->passes())
            ->map(fn (array $fieldRules, string $field) => (string) $this->input($field))
            ->all();
    }
}
