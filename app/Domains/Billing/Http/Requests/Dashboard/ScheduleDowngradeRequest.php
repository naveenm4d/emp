<?php

namespace App\Domains\Billing\Http\Requests\Dashboard;

use App\Domains\Billing\Models\Payment;
use App\Domains\Client\Enums\ClientPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Moving from Business to Celebration or Starter when Business ends. */
class ScheduleDowngradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('create', Payment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::in([ClientPlan::Celebration->value, ClientPlan::Starter->value])],
        ];
    }

    public function plan(): ClientPlan
    {
        return ClientPlan::from((string) $this->validated('plan'));
    }
}
