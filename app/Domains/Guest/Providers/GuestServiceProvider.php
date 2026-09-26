<?php

namespace App\Domains\Guest\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Event\Events\EventRegistrationTypeChanged;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Guest\Contracts\GuestRepositoryInterface;
use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Contracts\RegistrationAnswerRepositoryInterface;
use App\Domains\Guest\Listeners\ApproveGuestListGuests;
use App\Domains\Guest\Listeners\SyncGuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Policies\GuestPolicy;
use App\Domains\Guest\Repositories\GuestRepository;
use App\Domains\Guest\Repositories\RegistrationAnswerRepository;
use App\Domains\Guest\Services\GuestQueryService;
use App\Domains\Guest\Services\GuestService;
use App\Domains\Rsvp\Events\RsvpResponded;
use App\Domains\Rsvp\Events\RsvpSent;

class GuestServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        GuestRepositoryInterface::class => GuestRepository::class,
        GuestServiceInterface::class => GuestService::class,
        GuestQueryServiceInterface::class => GuestQueryService::class,
        RegistrationAnswerRepositoryInterface::class => RegistrationAnswerRepository::class,
    ];

    protected array $policies = [
        Guest::class => GuestPolicy::class,
    ];

    protected array $listen = [
        RsvpSent::class => [SyncGuestRsvpStatus::class],
        RsvpResponded::class => [SyncGuestRsvpStatus::class],
        EventRegistrationTypeChanged::class => [ApproveGuestListGuests::class],
    ];
}
