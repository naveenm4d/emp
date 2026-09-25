<?php

namespace App\Domains\Event\Enums;

use App\Core\Enums\Concerns\HasValues;

enum EventState: string
{
    use HasValues;

    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published, self::Cancelled],
            self::Published => [self::Draft, self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return $this !== self::Cancelled;
    }
}
