<?php

namespace App\Domains\Staff\Http\Requests\Internal;

use App\Domains\Staff\DTOs\CreateStaffMemberData;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guarded by `can:staff.create` on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(StaffMember::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::enum(StaffPermission::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function toData(): CreateStaffMemberData
    {
        return CreateStaffMemberData::fromArray($this->validated());
    }
}
