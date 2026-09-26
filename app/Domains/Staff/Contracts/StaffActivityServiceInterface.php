<?php

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Models\StaffActivity;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Database\Eloquent\Model;

/**
 * The activity log: what staff did in the admin console.
 */
interface StaffActivityServiceInterface
{
    /**
     * @param  array<string, mixed>  $changes  before / after, or the request payload (no secrets)
     */
    public function record(
        ?StaffMember $staff,
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $clientId = null,
        array $changes = [],
        ?string $note = null,
    ): StaffActivity;
}
