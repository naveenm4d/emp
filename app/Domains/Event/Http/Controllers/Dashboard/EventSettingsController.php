<?php

namespace App\Domains\Event\Http\Controllers\Dashboard;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Event\Contracts\RegistrationQuestionRepositoryInterface;
use App\Domains\Event\Contracts\RegistrationSettingsServiceInterface;
use App\Domains\Event\Enums\DietaryOption;
use App\Domains\Event\Enums\FieldRequirement;
use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Http\Requests\Dashboard\UpdateRegistrationSettingsRequest;
use App\Domains\Event\Http\Resources\EventResource;
use App\Domains\Event\Http\Resources\RegistrationQuestionResource;
use App\Domains\Event\Models\Event;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The event's Settings tab: what guests are asked when they register
 * (public link) or RSVP (personal link).
 */
class EventSettingsController extends InertiaController
{
    public function __construct(
        private readonly RegistrationSettingsServiceInterface $settings,
    ) {}

    public function edit(Event $event, RegistrationQuestionRepositoryInterface $questions): Response
    {
        $this->authorize('view', $event);
        $event->load(['templateVersion.template.latestVersion', 'publicLink', 'registrationQuestions']);

        return Inertia::render('client/events/settings', [
            'event' => EventResource::make($event),
            'settings' => $event->registrationSettings()->toArray(),
            // For a datetime-local input, in the app's time zone.
            'responsesLockAt' => $event->responses_lock_at?->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'questions' => RegistrationQuestionResource::collection($event->registrationQuestions)->resolve(),
            'answeredQuestionIds' => $questions->answeredIds($event->id),
            'questionTypes' => QuestionType::options(),
            'dietaryOptions' => DietaryOption::options(),
            'fieldRequirements' => FieldRequirement::options(),
        ]);
    }

    public function update(UpdateRegistrationSettingsRequest $request, Event $event): RedirectResponse
    {
        $this->settings->update($event, $request->toData());

        return $this->backWithSuccess('Settings saved.');
    }
}
