<?php

namespace App\Domains\Staff\Http\Controllers\Admin;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Staff\Contracts\StaffActivityQueryServiceInterface;
use App\Domains\Staff\Contracts\StaffMemberServiceInterface;
use App\Domains\Staff\DTOs\ActivityFilters;
use App\Domains\Staff\Http\Resources\StaffActivityResource;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The activity log: everything staff did in the admin console, newest first.
 */
class ActivityController extends InertiaController
{
    public function __construct(
        private readonly StaffActivityQueryServiceInterface $activities,
    ) {}

    public function index(Request $request, StaffMemberServiceInterface $staff): Response
    {
        $filters = ActivityFilters::fromArray($request->query());

        return Inertia::render('admin/activity/index', [
            'activities' => StaffActivityResource::collection($this->activities->paginate($filters)),
            'filters' => $filters->toQuery(),
            'staff' => array_map(
                fn (StaffMember $member) => ['value' => $member->id, 'label' => $member->name],
                $staff->paginate()->items(),
            ),
            'actions' => array_map(fn (string $action) => ['value' => $action, 'label' => $action], $this->activities->actions()),
        ]);
    }
}
