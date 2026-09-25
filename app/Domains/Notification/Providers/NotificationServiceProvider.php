<?php

namespace App\Domains\Notification\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Invitation\Events\InvitationSent;
use App\Domains\Notification\Channels\ChannelRegistry;
use App\Domains\Notification\Channels\EmailChannel;
use App\Domains\Notification\Channels\SmsChannel;
use App\Domains\Notification\Channels\WhatsAppChannel;
use App\Domains\Notification\Console\Commands\RequeuePendingNotificationsCommand;
use App\Domains\Notification\Contracts\NotificationDispatcherInterface;
use App\Domains\Notification\Contracts\NotificationQueryServiceInterface;
use App\Domains\Notification\Contracts\NotificationRepositoryInterface;
use App\Domains\Notification\Contracts\NotificationServiceInterface;
use App\Domains\Notification\Listeners\SendInvitationMessage;
use App\Domains\Notification\Models\Notification;
use App\Domains\Notification\Policies\NotificationPolicy;
use App\Domains\Notification\Repositories\NotificationRepository;
use App\Domains\Notification\Services\NotificationDispatcher;
use App\Domains\Notification\Services\NotificationQueryService;
use App\Domains\Notification\Services\NotificationService;

class NotificationServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        NotificationRepositoryInterface::class => NotificationRepository::class,
        NotificationDispatcherInterface::class => NotificationDispatcher::class,
        NotificationServiceInterface::class => NotificationService::class,
        NotificationQueryServiceInterface::class => NotificationQueryService::class,
    ];

    protected array $policies = [
        Notification::class => NotificationPolicy::class,
    ];

    protected array $listen = [
        InvitationSent::class => [SendInvitationMessage::class],
    ];

    public function register(): void
    {
        $this->app->singleton(ChannelRegistry::class, fn ($app) => new ChannelRegistry([
            $app->make(WhatsAppChannel::class),
            $app->make(EmailChannel::class),
            $app->make(SmsChannel::class),
        ]));
    }

    protected function bootDomain(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RequeuePendingNotificationsCommand::class]);
        }
    }
}
