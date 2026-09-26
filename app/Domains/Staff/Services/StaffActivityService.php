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
        private readonly Request $request,
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
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 497),
            'created_at' => now(),
        ]);

        // The request's action is logged; the middleware mustn't log it again.
        $this->request->attributes->set(StaffActivity::RECORDED, true);

        return $activity;
    }
}
