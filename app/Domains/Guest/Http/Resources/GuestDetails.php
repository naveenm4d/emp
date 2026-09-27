<?php

namespace App\Domains\Guest\Http\Resources;

use App\Domains\Event\Models\Event;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Models\RegistrationAnswer;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Http\Resources\NotificationResource;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Http\Resources\RsvpResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The `guestDetails` prop of the guest and RSVP pages: all of the guest picked
 * with `?guest=` RSVP links and every message sent to them (newest first), and
 * their answers to custom questions. Null when no guest is open.
 */
final readonly class GuestDetails
{
    public function __construct(
        private RsvpQueryServiceInterface $rsvps,
        private NotificationQueryServiceInterface $notifications,
    ) {}

    /**
     * @return array{guest_id: string, rsvps: array<int, mixed>, messages: array<int, mixed>, answers: array<int, array{question: string, type: string, value: mixed}>}|null
     */
    public function forRequest(Event $event, Request $request): ?array
    {
        $guestId = $request->query('guest');

        if (! is_string($guestId) || ! Str::isUuid($guestId)) {
            return null;
        }

        /** @var Guest $guest */
        $guest = $event->guests()->findOrFail($guestId);

        return [
            'guest_id' => $guest->id,
            'rsvps' => RsvpResource::collection($this->rsvps->forGuest($guest))->resolve($request),
            'messages' => NotificationResource::collection($this->notifications->forGuest($guest))->resolve($request),
            // Answers to the event's custom questions, in form order.
            'answers' => $guest->answers()
                ->with('question')
                ->get()
                ->sortBy('question.sort_order')
                ->map(fn (RegistrationAnswer $answer) => [
                    'question' => $answer->question->label,
                    'type' => $answer->question->type->value,
                    'value' => $answer->value,
                ])
                ->values()
                ->all(),
        ];
    }
}
