<?php

namespace App\Domains\Guest\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Models\RegistrationAnswer;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Http\Resources\NotificationResource;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Http\Resources\RsvpResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON for the guest details panel: all of the guest's RSVP links and every
 * message sent to them (newest first), and their answers to custom questions.
 */
class GuestDetailsController extends InertiaController
{
    public function __invoke(
        Request $request,
        Guest $guest,
        RsvpQueryServiceInterface $rsvps,
        NotificationQueryServiceInterface $notifications,
    ): JsonResponse {
        $this->authorize('view', $guest);

        return response()->json([
            'rsvps' => RsvpResource::collection($rsvps->forGuest($guest))->resolve($request),
            'messages' => NotificationResource::collection($notifications->forGuest($guest))->resolve($request),
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
                ->values(),
        ]);
    }
}
