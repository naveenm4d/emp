<?php

namespace App\Domains\Guest\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Guest\Contracts\GuestQueryServiceInterface;
use App\Domains\Guest\Contracts\GuestRepositoryInterface;
use App\Domains\Guest\Contracts\GuestServiceInterface;
use App\Domains\Guest\Listeners\SyncGuestRsvpStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Guest\Policies\GuestPolicy;
use App\Domains\Guest\Repositories\GuestRepository;
use App\Domains\Guest\Services\GuestQueryService;
use App\Domains\Guest\Services\GuestService;
use App\Domains\Invitation\Events\InvitationResponded;
use App\Domains\Invitation\Events\InvitationSent;

class GuestServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        GuestRepositoryInterface::class => GuestRepository::class,
        GuestServiceInterface::class => GuestService::class,
        GuestQueryServiceInterface::class => GuestQueryService::class,
    ];

    protected array $policies = [
        Guest::class => GuestPolicy::class,
    ];

    protected array $listen = [
        InvitationSent::class => [SyncGuestRsvpStatus::class],
        InvitationResponded::class => [SyncGuestRsvpStatus::class],
    ];
}
