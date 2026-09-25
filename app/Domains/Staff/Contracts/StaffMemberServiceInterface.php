<?php

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\DTOs\CreateStaffMemberData;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StaffMemberServiceInterface
{
    public function create(CreateStaffMemberData $data): StaffMember;

    public function recordLogin(StaffMember $staff): void;

    /** @return LengthAwarePaginator<int, StaffMember> */
    public function paginate(): LengthAwarePaginator;
}
