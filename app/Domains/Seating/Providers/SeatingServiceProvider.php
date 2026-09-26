<?php

namespace App\Domains\Seating\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Guest\Events\GuestPartyChanged;
use App\Domains\Seating\Contracts\EventTableRepositoryInterface;
use App\Domains\Seating\Contracts\SeatAssignmentRepositoryInterface;
use App\Domains\Seating\Contracts\SeatingQueryServiceInterface;
use App\Domains\Seating\Contracts\SeatingServiceInterface;
use App\Domains\Seating\Listeners\SyncGuestSeats;
use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Policies\EventTablePolicy;
use App\Domains\Seating\Repositories\EventTableRepository;
use App\Domains\Seating\Repositories\SeatAssignmentRepository;
use App\Domains\Seating\Services\SeatingQueryService;
use App\Domains\Seating\Services\SeatingService;

class SeatingServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EventTableRepositoryInterface::class => EventTableRepository::class,
        SeatAssignmentRepositoryInterface::class => SeatAssignmentRepository::class,
        SeatingServiceInterface::class => SeatingService::class,
        SeatingQueryServiceInterface::class => SeatingQueryService::class,
    ];

    protected array $policies = [
        EventTable::class => EventTablePolicy::class,
    ];

    protected array $listen = [
        GuestPartyChanged::class => [SyncGuestSeats::class],
    ];
}
