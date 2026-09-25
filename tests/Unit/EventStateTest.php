<?php

use App\Domains\Event\Enums\EventState;

it('allows only the defined transitions', function (EventState $from, EventState $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [EventState::Draft, EventState::Published, true],
    [EventState::Draft, EventState::Cancelled, true],
    [EventState::Published, EventState::Draft, true],
    [EventState::Published, EventState::Cancelled, true],
    [EventState::Cancelled, EventState::Published, false],
    [EventState::Cancelled, EventState::Draft, false],
]);
