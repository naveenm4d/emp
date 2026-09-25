<?php

namespace App\Domains\Staff\Providers;

use App\Core\Providers\DomainServiceProvider;
use App\Domains\Staff\Console\Commands\CreateStaffMemberCommand;
use App\Domains\Staff\Contracts\PlatformStatsServiceInterface;
use App\Domains\Staff\Contracts\StaffMemberRepositoryInterface;
use App\Domains\Staff\Contracts\StaffMemberServiceInterface;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Models\StaffMember;
use App\Domains\Staff\Repositories\StaffMemberRepository;
use App\Domains\Staff\Services\PlatformStatsService;
use App\Domains\Staff\Services\StaffMemberService;
use Illuminate\Support\Facades\Gate;

class StaffServiceProvider extends DomainServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        StaffMemberRepositoryInterface::class => StaffMemberRepository::class,
        StaffMemberServiceInterface::class => StaffMemberService::class,
        PlatformStatsServiceInterface::class => PlatformStatsService::class,
    ];

    protected function bootDomain(): void
    {
        // Super admins pass every staff permission check. Returning null (not
        // false) for everyone else lets the regular gates / policies decide.
        Gate::before(fn (object $user) => $user instanceof StaffMember && $user->isSuperAdmin() ? true : null);

        foreach (StaffPermission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (object $user) => $user instanceof StaffMember && $user->hasPermission($permission),
            );
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CreateStaffMemberCommand::class]);
        }
    }
}
