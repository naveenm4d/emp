<?php

namespace App\Domains\Staff\Models;

use App\Domains\Client\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a staff member did in the admin console (the activity log).
 *
 * @property string $id
 * @property string|null $staff_member_id
 * @property string $action route name, e.g. admin.clients.plan
 * @property string $description readable summary
 * @property string|null $subject_type the model acted on (morph class)
 * @property string|null $subject_id
 * @property string|null $client_id the client concerned, for a client's history
 * @property array<string, mixed>|null $changes before / after, or the request payload (without secrets)
 * @property string|null $note why (required for plan changes)
 * @property string|null $ip
 * @property string|null $user_agent
 * @property-read StaffMember|null $staffMember
 * @property-read Client|null $client
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'staff_member_id', 'action', 'description', 'subject_type', 'subject_id', 'client_id',
    'changes', 'note', 'ip', 'user_agent', 'created_at',
])]
class StaffActivity extends Model
{
    use HasUuids;

    /** Request attribute set once an action was logged explicitly, so the middleware doesn't log it again. */
    public const string RECORDED = 'staff_activity_recorded';

    public const null UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
