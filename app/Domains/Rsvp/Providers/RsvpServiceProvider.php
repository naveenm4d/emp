<?php

namespace App\Domains\Rsvp\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Rsvp\Console\Commands\SendRsvpRemindersCommand;
use App\Domains\Rsvp\Contracts\RsvpQueryServiceInterface;
use App\Domains\Rsvp\Contracts\RsvpRepositoryInterface;
use App\Domains\Rsvp\Contracts\RsvpServiceInterface;
use App\Domains\Rsvp\Models\Rsvp;
use App\Domains\Rsvp\Policies\RsvpPolicy;
use App\Domains\Rsvp\Repositories\RsvpRepository;
use App\Domains\Rsvp\Services\RsvpQueryService;
use App\Domains\Rsvp\Services\RsvpService;

class RsvpServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        RsvpRepositoryInterface::class => RsvpRepository::class,
        RsvpServiceInterface::class => RsvpService::class,
        RsvpQueryServiceInterface::class => RsvpQueryService::class,
    ];

    protected array $policies = [
        Rsvp::class => RsvpPolicy::class,
    ];

    protected function bootDomain(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SendRsvpRemindersCommand::class]);
        }
    }
}
