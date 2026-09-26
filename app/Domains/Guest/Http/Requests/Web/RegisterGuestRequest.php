<?php

namespace App\Domains\Guest\Http\Requests\Web;

use App\Core\Exceptions\NotFoundException;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Event\Enums\FieldRequirement;
use App\Domains\Event\Models\EventLink;
use App\Domains\Guest\DTOs\GuestData;
use App\Domains\Guest\DTOs\RegistrationDetailsData;
use App\Domains\Guest\Http\Requests\GuestRules;
use App\Domains\Guest\Http\Requests\RegistrationDetailsRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-registration through the event's public link. What is asked besides
 * the name comes from the event's settings.
 */
class RegisterGuestRequest extends FormRequest
{
    private ?EventLink $link = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $event = $this->link()->event;
        $settings = $event->registrationSettings();
        $contact = GuestRules::contact();

        $email = $settings->requirement('email');
        $phone = $settings->requirement('phone');

        // A public registrant must leave a way to be contacted.
        if (! $email->isCollected() && ! $phone->isCollected()) {
            $email = $phone = FieldRequirement::Optional;
        }

        return [
            ...RegistrationDetailsRules::for($event),
            'name' => $contact['name'],
            'email' => match (true) {
                $email === FieldRequirement::Off => ['exclude'],
                $email === FieldRequirement::Required, ! $phone->isCollected() => ['required', 'email', 'max:255'],
                $phone === FieldRequirement::Optional => ['required_without:phone', ...$contact['email']],
                default => $contact['email'],
            },
            'phone' => match (true) {
                $phone === FieldRequirement::Off => ['exclude'],
                $phone === FieldRequirement::Required, ! $email->isCollected() => ['required', ...array_diff($contact['phone'], ['nullable'])],
                default => $contact['phone'],
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return RegistrationDetailsRules::attributes($this->link()->event);
    }

    /** The event's public link the form was posted to; guest links can't register. */
    public function link(): EventLink
    {
        if ($this->link === null) {
            $link = app(EventLinkServiceInterface::class)->resolve((string) $this->route('code'));

            if ($link->isForGuest()) {
                throw new NotFoundException('Link not found.');
            }

            $this->link = $link->load('event.registrationQuestions');
        }

        return $this->link;
    }

    public function toData(): GuestData
    {
        return GuestData::fromArray($this->safe()->only(['name', 'email', 'phone']));
    }

    public function details(): RegistrationDetailsData
    {
        return RegistrationDetailsData::fromValidated($this->validated(), $this->link()->event);
    }
}
