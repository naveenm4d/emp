<?php

namespace App\Domains\Invitation\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Invitation\Contracts\InvitationQueryServiceInterface;
use App\Domains\Invitation\Contracts\InvitationRepositoryInterface;
use App\Domains\Invitation\Contracts\InvitationServiceInterface;
use App\Domains\Invitation\Models\Invitation;
use App\Domains\Invitation\Policies\InvitationPolicy;
use App\Domains\Invitation\Repositories\InvitationRepository;
use App\Domains\Invitation\Services\InvitationQueryService;
use App\Domains\Invitation\Services\InvitationService;

class InvitationServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        InvitationRepositoryInterface::class => InvitationRepository::class,
        InvitationServiceInterface::class => InvitationService::class,
        InvitationQueryServiceInterface::class => InvitationQueryService::class,
    ];

    protected array $policies = [
        Invitation::class => InvitationPolicy::class,
    ];
}
