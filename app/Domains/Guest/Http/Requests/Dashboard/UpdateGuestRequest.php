<?php

namespace App\Domains\Guest\Http\Requests\Dashboard;

use App\Domains\Guest\DTOs\GuestContact;
use App\Domains\Guest\DTOs\UpdateGuestData;
use App\Domains\Guest\Http\Requests\GuestRules;
use App\Domains\Guest\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->guest()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return collect([
            ...GuestRules::contact(),
            ...GuestRules::invitedParty(),
            'notes' => ['nullable', 'string', 'max:2000'],
            'invitation_message' => ['nullable', 'string', 'max:1000'],
            'reminder_message' => ['nullable', 'string', 'max:1000'],
        ])
            ->map(fn (array $rules) => ['sometimes', ...$rules])
            ->all();
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // The guest must keep an email or a phone after the change.
                $email = $this->has('email') ? GuestContact::email($this->input('email')) : $this->guest()->email;
                $phone = $this->has('phone') ? GuestContact::phone($this->input('phone')) : $this->guest()->phone;

                if ($email === null && $phone === null) {
                    $validator->errors()->add('email', GuestRules::CONTACT_REQUIRED);
                }
            },
        ];
    }

    public function toData(): UpdateGuestData
    {
        return UpdateGuestData::fromArray($this->validated());
    }

    private function guest(): Guest
    {
        /** @var Guest */
        return $this->route('guest');
    }
}
