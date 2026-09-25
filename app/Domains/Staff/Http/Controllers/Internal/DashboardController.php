<?php

namespace App\Domains\Staff\Http\Controllers\Internal;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Staff\Contracts\PlatformStatsServiceInterface;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends InertiaController
{
    public function __construct(
        private readonly PlatformStatsServiceInterface $stats,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var StaffMember $staff */
        $staff = $request->user('staff');

        return Inertia::render('internal/dashboard', [
            'stats' => $staff->hasPermission(StaffPermission::StatsRead)
                ? $this->stats->overview()->toArray()
                : null,
        ]);
    }
}
