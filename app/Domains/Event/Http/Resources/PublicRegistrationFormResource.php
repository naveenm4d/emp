<?php

namespace App\Domains\Event\Http\Resources;

use App\Domains\Event\DTOs\RegistrationSettings;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The form a guest fills in when registering or RSVPing, from the event's
 * settings. On a personal link the plus-ones and children follow what the
 * client invited that guest with.
 *
 * @mixin Event
 */
class PublicRegistrationFormResource extends JsonResource
{
    private ?Guest $guest = null;

    public static function forRegistration(Event $event): self
    {
        return new self($event);
    }

    /** Without a guest (preview), the event's settings apply. */
    public static function forRsvp(Event $event, ?Guest $guest = null): self
    {
        $resource = new self($event);
        $resource->guest = $guest;

        return $resource;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $settings = $this->registrationSettings();
        $allowance = $this->guest?->partyAllowance($settings) ?? [
            'additional' => $settings->plusOnes ? $settings->maxAdditionalGuests : 0,
            'children' => $settings->children ? RegistrationSettings::MAX_ADDITIONAL_GUESTS : 0,
        ];

        return [
            'contact' => $settings->toArray()['contact'],
            'allow_maybe' => $settings->allowMaybe,
            'plus_ones' => $allowance['additional'] > 0,
            'max_additional_guests' => $allowance['additional'],
            'children' => $allowance['children'] > 0,
            'max_children' => $allowance['children'],
            'dietary_options' => $settings->collectsDietaryRestrictions()
                ? array_map(fn ($option) => ['value' => $option->value, 'label' => $option->label()], $settings->dietaryOptions)
                : [],
            'dietary_notes' => $settings->dietary && $settings->dietaryNotes,
            'questions' => RegistrationQuestionResource::collection($this->registrationQuestions)->resolve($request),
        ];
    }
}
