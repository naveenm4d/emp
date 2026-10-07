<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Enums\EventType;
use App\Domains\Event\Enums\QuestionType;
use App\Domains\Event\Enums\RegistrationType;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Models\RegistrationQuestion;
use App\Domains\Guest\Models\RegistrationAnswer;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->actingAs($this->client->owner, 'client');
});

/**
 * A complete, valid Settings form.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'settings' => [
            'contact' => ['email' => 'optional', 'phone' => 'optional', 'address' => 'off', 'company' => 'off', 'job_title' => 'off'],
            'attendance' => ['allow_maybe' => false],
            'party' => ['plus_ones' => false, 'max_additional_guests' => 1, 'children' => false, 'max_children' => 20],
            'dietary' => ['enabled' => false, 'options' => [], 'notes' => false],
            'responses' => ['editable' => false],
        ],
        'responses_lock_at' => null,
        'questions' => [],
    ], $overrides);
}

it('starts from defaults that suit the event type', function (EventType $type, RegistrationType $registration, array $expected) {
    $event = Event::factory()->for($this->client)->create([
        'event_type' => $type,
        'registration_type' => $registration,
        'event_registration_settings' => null,
    ]);

    $this->get("/app/events/{$event->id}/settings")
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($expected) {
            $page->component('client/events/settings');

            foreach ($expected as $key => $value) {
                $page->where("settings.{$key}", $value);
            }
        });
})->with([
    'guest-list wedding: plus-ones, contact already known' => [EventType::Wedding, RegistrationType::GuestListOnly, [
        'party.plus_ones' => true, 'contact.email' => 'off', 'contact.phone' => 'off', 'contact.company' => 'off', 'dietary.enabled' => false,
    ]],
    'open conference: work details and dietary' => [EventType::Conference, RegistrationType::Open, [
        'party.plus_ones' => false, 'contact.email' => 'required', 'contact.phone' => 'optional', 'contact.company' => 'optional', 'dietary.enabled' => true,
    ]],
]);

it('saves the settings and the custom questions in order', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->from("/app/events/{$event->id}/settings")
        ->put("/app/events/{$event->id}/settings", settingsPayload([
            'settings' => [
                'contact' => ['company' => 'required'],
                'attendance' => ['allow_maybe' => true],
                'party' => ['plus_ones' => true, 'max_additional_guests' => 3, 'children' => true, 'max_children' => 2],
                'dietary' => ['enabled' => true, 'options' => ['vegan', 'halal'], 'notes' => true],
                'responses' => ['editable' => true],
            ],
            'responses_lock_at' => '2027-01-10T18:30',
            'questions' => [
                ['label' => 'T-shirt size', 'type' => 'select', 'required' => true, 'options' => ['S', 'M', 'L']],
                ['label' => 'Any special requirements?', 'type' => 'textarea', 'required' => false],
            ],
        ]))
        ->assertRedirect("/app/events/{$event->id}/settings")
        ->assertSessionHas('success', 'Settings saved.');

    $event->refresh();
    $settings = $event->registrationSettings();

    expect($settings->requirement('company')->value)->toBe('required')
        ->and($settings->allowMaybe)->toBeTrue()
        ->and($settings->maxAdditionalGuests)->toBe(3)
        ->and($settings->maxChildren)->toBe(2)
        ->and(array_map(fn ($option) => $option->value, $settings->dietaryOptions))->toBe(['vegan', 'halal'])
        ->and($event->responses_lock_at->format('Y-m-d H:i'))->toBe('2027-01-10 18:30')
        ->and($event->registrationQuestions->map->only(['label', 'type', 'required', 'options', 'sort_order'])->all())->toEqual([
            ['label' => 'T-shirt size', 'type' => QuestionType::Select, 'required' => true, 'options' => ['S', 'M', 'L'], 'sort_order' => 0],
            ['label' => 'Any special requirements?', 'type' => QuestionType::Textarea, 'required' => false, 'options' => null, 'sort_order' => 1],
        ]);
});

it('updates, reorders and removes existing questions, removing their answers too', function () {
    $event = Event::factory()->for($this->client)->create();
    $kept = RegistrationQuestion::factory()->for($event)->create(['label' => 'Old label', 'sort_order' => 0]);
    $removed = RegistrationQuestion::factory()->for($event)->create(['sort_order' => 1]);
    RegistrationAnswer::factory()->for($removed, 'question')->create();

    $this->put("/app/events/{$event->id}/settings", settingsPayload([
        'questions' => [
            ['label' => 'New question', 'type' => 'checkbox', 'required' => false],
            ['id' => $kept->id, 'label' => 'New label', 'type' => 'text', 'required' => true],
        ],
    ]))->assertSessionHas('success');

    expect($kept->fresh())
        ->label->toBe('New label')
        ->required->toBeTrue()
        ->sort_order->toBe(1)
        ->and(RegistrationQuestion::find($removed->id))->toBeNull()
        ->and(RegistrationAnswer::count())->toBe(0)
        ->and($event->registrationQuestions()->pluck('label')->all())->toBe(['New question', 'New label']);
});

it('refuses to change the type of a question guests already answered', function () {
    $event = Event::factory()->for($this->client)->create();
    $question = RegistrationQuestion::factory()->for($event)->create();
    RegistrationAnswer::factory()->for($question, 'question')->create();

    $this->put("/app/events/{$event->id}/settings", settingsPayload([
        'questions' => [['id' => $question->id, 'label' => 'Size', 'type' => 'select', 'required' => false, 'options' => ['S', 'M']]],
    ]))->assertSessionHas('error', "Guests have already answered this question, so its type can't be changed. Add a new question instead.");

    expect($question->fresh()->type)->toBe(QuestionType::Text);
});

it('validates the questions', function () {
    $event = Event::factory()->for($this->client)->create();

    $this->put("/app/events/{$event->id}/settings", settingsPayload([
        'questions' => [
            ['label' => '', 'type' => 'text', 'required' => false],
            ['label' => 'Size', 'type' => 'select', 'required' => false],
            ['label' => 'Workshop', 'type' => 'radio', 'required' => false, 'options' => ['AI', ' ai ']],
        ],
    ]))->assertSessionHasErrors([
        'questions.0.label' => 'Write the question.',
        'questions.1.options' => 'Add the choices guests pick from.',
    ]);

    $this->put("/app/events/{$event->id}/settings", settingsPayload([
        'questions' => [['label' => 'Workshop', 'type' => 'radio', 'required' => false, 'options' => ['AI', ' ai ']]],
    ]))->assertSessionHasErrors(['questions.0.options' => 'Add at least two different choices.']);

    expect(RegistrationQuestion::count())->toBe(0);
});

it('requires an email or phone on events with public registration', function () {
    $event = Event::factory()->for($this->client)->create(['registration_type' => RegistrationType::Open]);

    $this->put("/app/events/{$event->id}/settings", settingsPayload([
        'settings' => ['contact' => ['email' => 'off', 'phone' => 'off']],
    ]))->assertSessionHasErrors(['settings.contact.email' => 'Ask for an email or a phone number, so you can reach people who register.']);
});

it('lets guest-list events turn off both email and phone', function () {
    $event = Event::factory()->for($this->client)->create(['registration_type' => RegistrationType::GuestListOnly]);

    $this->put("/app/events/{$event->id}/settings", settingsPayload([
        'settings' => ['contact' => ['email' => 'off', 'phone' => 'off']],
    ]))->assertSessionHas('success');
});

it('refuses changes to cancelled events', function () {
    $event = Event::factory()->for($this->client)->cancelled()->create();

    $this->put("/app/events/{$event->id}/settings", settingsPayload(['settings' => ['attendance' => ['allow_maybe' => true]]]))
        ->assertSessionHas('error', 'Cancelled events can no longer be changed.');

    expect($event->fresh()->registrationSettings()->allowMaybe)->toBeFalse();
});

it('forbids another client\'s event settings', function () {
    $event = Event::factory()->create();

    $this->get("/app/events/{$event->id}/settings")->assertForbidden();
    $this->put("/app/events/{$event->id}/settings", settingsPayload())->assertForbidden();
});
