<?php

namespace App\Domains\Staff\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Domains\Staff\Models\StaffMember;

/**
 * @extends RepositoryInterface<StaffMember>
 */
interface StaffMemberRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?StaffMember;
}
