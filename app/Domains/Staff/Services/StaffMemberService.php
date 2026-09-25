<?php

namespace App\Domains\Staff\Services;

use App\Core\Exceptions\ConflictException;
use App\Core\Services\BaseService;
use App\Domains\Staff\Contracts\StaffMemberRepositoryInterface;
use App\Domains\Staff\Contracts\StaffMemberServiceInterface;
use App\Domains\Staff\DTOs\CreateStaffMemberData;
use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StaffMemberService extends BaseService implements StaffMemberServiceInterface
{
    public function __construct(
        private readonly StaffMemberRepositoryInterface $staff,
    ) {}

    public function create(CreateStaffMemberData $data): StaffMember
    {
        if ($this->staff->findByEmail($data->email)) {
            throw new ConflictException('A staff member with this email already exists.');
        }

        $permissions = $data->permissions ?? $data->role->defaultPermissions();

        /** @var StaffMember */
        return $this->staff->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
            'role' => $data->role,
            'permissions' => array_values(array_unique(array_map(
                fn (StaffPermission $permission) => $permission->value,
                $permissions,
            ))),
            'is_active' => true,
        ]);
    }

    public function recordLogin(StaffMember $staff): void
    {
        $this->staff->update($staff, ['last_login_at' => now()]);
    }

    public function paginate(): LengthAwarePaginator
    {
        return $this->staff->paginate(config('emp.per_page'));
    }
}
