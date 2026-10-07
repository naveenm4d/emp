<?php

namespace App\Domains\Client\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Client\Contracts\ClientPlanServiceInterface;
use App\Domains\Client\Contracts\ClientQueryServiceInterface;
use App\Domains\Client\Contracts\ClientRepositoryInterface;
use App\Domains\Client\Contracts\ClientServiceInterface;
use App\Domains\Client\Contracts\ClientTeamServiceInterface;
use App\Domains\Client\Contracts\ClientUserRepositoryInterface;
use App\Domains\Client\Models\ClientUser;
use App\Domains\Client\Policies\ClientUserPolicy;
use App\Domains\Client\Repositories\ClientRepository;
use App\Domains\Client\Repositories\ClientUserRepository;
use App\Domains\Client\Services\ClientPlanService;
use App\Domains\Client\Services\ClientQueryService;
use App\Domains\Client\Services\ClientService;
use App\Domains\Client\Services\ClientTeamService;
use Illuminate\Auth\Notifications\ResetPassword;

class ClientServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ClientRepositoryInterface::class => ClientRepository::class,
        ClientServiceInterface::class => ClientService::class,
        ClientQueryServiceInterface::class => ClientQueryService::class,
        ClientPlanServiceInterface::class => ClientPlanService::class,
        ClientUserRepositoryInterface::class => ClientUserRepository::class,
        ClientTeamServiceInterface::class => ClientTeamService::class,
    ];

    /** @var array<class-string, class-string> */
    protected array $policies = [
        ClientUser::class => ClientUserPolicy::class,
    ];

    protected function bootDomain(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return $notifiable instanceof ClientUser
                ? route('client.password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()])
                : url('/');
        });
    }
}
