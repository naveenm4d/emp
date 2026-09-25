<?php

namespace App\Domains\Staff\Http\Controllers\Internal;

use App\Core\Http\Controllers\InertiaController;
use App\Domains\Staff\Contracts\StaffMemberServiceInterface;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Http\Requests\Internal\StoreStaffMemberRequest;
use App\Domains\Staff\Http\Resources\StaffMemberResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StaffMemberController extends InertiaController
{
    public function __construct(
        private readonly StaffMemberServiceInterface $staff,
    ) {}

    public function index(): Response
    {
        return Inertia::render('internal/staff/index', [
            'staff' => StaffMemberResource::collection($this->staff->paginate()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('internal/staff/create', [
            'roles' => StaffRole::options(),
            'permissions' => StaffPermission::options(),
            'roleDefaults' => collect(StaffRole::cases())->mapWithKeys(fn (StaffRole $role) => [
                $role->value => array_map(fn (StaffPermission $p) => $p->value, $role->defaultPermissions()),
            ]),
        ]);
    }

    public function store(StoreStaffMemberRequest $request): RedirectResponse
    {
        $this->staff->create($request->toData());

        return $this->toRouteWithSuccess('internal.staff.index', 'Staff member created.');
    }
}
