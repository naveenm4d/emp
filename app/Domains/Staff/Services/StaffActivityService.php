<?php

namespace App\Domains\Staff\Services;

use App\Core\Services\BaseService;
use App\Domains\Staff\Contracts\StaffActivityRepositoryInterface;
use App\Domains\Staff\Contracts\StaffActivityServiceInterface;
use App\Domains\Staff\Models\StaffActivity;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffActivityService extends BaseService implements StaffActivityServiceInterface
{
    public function __construct(
        private readonly StaffActivityRepositoryInterface $activities,
    ) {}

    public function record(
        ?StaffMember $staff,
        string $action,
        string $description,
        ?Model $subject = null,
        ?string $clientId = null,
        array $changes = [],
        ?string $note = null,
    ): StaffActivity {
        // The current request, looked up now: services (and controllers) can outlive a request.
        /** @var Request $request */
        $request = app('request');

        /** @var StaffActivity $activity */
        $activity = $this->activities->create([
            'staff_member_id' => $staff?->id,
            'action' => $action,
            'description' => Str::limit($description, 497),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'client_id' => $clientId,
            'changes' => $changes === [] ? null : $changes,
            'note' => $note,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 497),
            'created_at' => now(),
        ]);

        // The request's action is logged; the middleware mustn't log it again.
        $request->attributes->set(StaffActivity::RECORDED, true);

        return $activity;
    }
}
