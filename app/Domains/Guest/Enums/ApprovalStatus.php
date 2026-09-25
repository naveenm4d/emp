<?php

namespace App\Domains\Guest\Enums;

use App\Core\Enums\Concerns\HasValues;

enum ApprovalStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Waitlisted = 'waitlisted';

    /** Pending and approved guests occupy a seat. */
    public function countsTowardCapacity(): bool
    {
        return in_array($this, self::capacityStatuses(), true);
    }

    /** @return list<self> */
    public static function capacityStatuses(): array
    {
        return [self::Pending, self::Approved];
    }
}
