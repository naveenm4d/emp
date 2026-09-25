<?php

namespace App\Domains\Staff\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Domains\Staff\Contracts\StaffMemberRepositoryInterface;
use App\Domains\Staff\Models\StaffMember;

/**
 * @extends BaseRepository<StaffMember>
 */
class StaffMemberRepository extends BaseRepository implements StaffMemberRepositoryInterface
{
    protected function model(): string
    {
        return StaffMember::class;
    }

    protected function resourceName(): string
    {
        return 'Staff member';
    }

    public function findByEmail(string $email): ?StaffMember
    {
        return $this->query()->where('email', mb_strtolower($email))->first();
    }
}
