<?php

namespace App\Domains\Rsvp\Http\Requests\Web;

use App\Core\Exceptions\NotFoundException;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Guest\DTOs\GuestContact;
use App\Domains\Guest\DTOs\RegistrationDetailsData;
use App\Domains\Guest\Http\Requests\GuestRules;
use App\Domains\Guest\Http\Requests\RegistrationDetailsRules;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Enums\RsvpStatus;
use App\Domains\Rsvp\Models\Rsvp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A guest's answer from their personal link: attending, not attending or
 * (if the event allows it) maybe, plus the details the event asks for.
 * Declining asks for nothing else.
 */
class RespondRsvpRequest extends FormRequest
{
    private ?Rsvp $rsvp = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $event = $this->rsvp()->event;

        $rules = [
            'attendance' => ['required', Rule::in(array_map(fn (RsvpStatus $status) => $status->value, $this->answers()))],
        ];

        if ($this->input('attendance') === RsvpStatus::Declined->value) {
            return [...$rules, 'note' => ['nullable', 'string', 'max:500']];
        }

        return [...RegistrationDetailsRules::for($event, $this->rsvp()->guest), ...$rules];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('attendance') === RsvpStatus::Declined->value) {
                    return;
                }

                // The guest must keep an email or a phone (only the ones the form asks for can change).
                $guest = $this->rsvp()->guest;
                $settings = $this->rsvp()->event->registrationSettings();
                $changes = fn (string $field) => $settings->requirement($field)->isCollected() && $this->has($field);
                $email = $changes('email') ? GuestContact::email($this->input('email')) : $guest->email;
                $phone = $changes('phone') ? GuestContact::phone($this->input('phone')) : $guest->phone;

                if ($email === null && $phone === null) {
                    $validator->errors()->add('phone', GuestRules::CONTACT_REQUIRED);
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return RegistrationDetailsRules::attributes($this->rsvp()->event);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['attendance.in' => 'Choose whether you will attend.'];
    }

    /** The guest's latest RSVP link, found from their personal link code. */
    public function rsvp(): Rsvp
    {
        if ($this->rsvp === null) {
            $link = app(EventLinkServiceInterface::class)->resolve((string) $this->route('code'));

            $this->rsvp = ($link->guest ? app(RsvpQueryServiceInterface::class)->findLatestForGuest($link->guest) : null)
                ?? throw new NotFoundException('RSVP link not found.');

            $this->rsvp->load(['event.registrationQuestions', 'guest']);
        }

        return $this->rsvp;
    }

    public function attendance(): RsvpStatus
    {
        return RsvpStatus::from($this->validated('attendance'));
    }

    /** Null when declining: nothing else is asked. */
    public function details(): ?RegistrationDetailsData
    {
        return $this->attendance() === RsvpStatus::Declined
            ? null
            : RegistrationDetailsData::fromValidated($this->validated(), $this->rsvp()->event, withContact: true);
    }

    /** The guest's note to the host; only sent with a decline. */
    public function note(): ?string
    {
        $note = trim((string) $this->validated('note'));

        return $note === '' ? null : $note;
    }

    /** @return list<RsvpStatus> */
    private function answers(): array
    {
        return $this->rsvp()->event->registrationSettings()->allowMaybe
            ? [RsvpStatus::Accepted, RsvpStatus::Declined, RsvpStatus::Maybe]
            : [RsvpStatus::Accepted, RsvpStatus::Declined];
    }
}
