<?php

namespace App\Domains\Event\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Event\Contracts\EventDesignServiceInterface;
use App\Domains\Event\Contracts\EventLinkServiceInterface;
use App\Domains\Event\Contracts\EventMediaServiceInterface;
use App\Domains\Event\Contracts\EventQueryServiceInterface;
use App\Domains\Event\Contracts\EventRepositoryInterface;
use App\Domains\Event\Contracts\EventServiceInterface;
use App\Domains\Event\Contracts\MapPreviewServiceInterface;
use App\Domains\Event\Contracts\RegistrationQuestionRepositoryInterface;
use App\Domains\Event\Contracts\RegistrationSettingsServiceInterface;
use App\Domains\Event\Events\EventDesignChanged;
use App\Domains\Event\Listeners\RenderEventInvitation;
use App\Domains\Event\Models\Event;
use App\Domains\Event\Policies\EventPolicy;
use App\Domains\Event\Repositories\EventRepository;
use App\Domains\Event\Repositories\RegistrationQuestionRepository;
use App\Domains\Event\Services\EventDesignService;
use App\Domains\Event\Services\EventLinkService;
use App\Domains\Event\Services\EventMediaService;
use App\Domains\Event\Services\EventQueryService;
use App\Domains\Event\Services\EventService;
use App\Domains\Event\Services\MapPreviewService;
use App\Domains\Event\Services\RegistrationSettingsService;

class EventServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EventRepositoryInterface::class => EventRepository::class,
        EventServiceInterface::class => EventService::class,
        EventQueryServiceInterface::class => EventQueryService::class,
        EventDesignServiceInterface::class => EventDesignService::class,
        EventMediaServiceInterface::class => EventMediaService::class,
        MapPreviewServiceInterface::class => MapPreviewService::class,
        EventLinkServiceInterface::class => EventLinkService::class,
        RegistrationQuestionRepositoryInterface::class => RegistrationQuestionRepository::class,
        RegistrationSettingsServiceInterface::class => RegistrationSettingsService::class,
    ];

    protected array $policies = [
        Event::class => EventPolicy::class,
    ];

    protected array $listen = [
        EventDesignChanged::class => [RenderEventInvitation::class],
    ];
}
