<?php

namespace App\Domains\Event\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Event\Enums\EventPeriod;
use App\Domains\Event\Enums\EventState;
use App\Domains\Event\Enums\RegistrationType;

final readonly class EventFilters extends DataTransferObject
{
    public function __construct(
        public ?EventState $state = null,
        public ?string $search = null,
        public ?EventPeriod $period = null,
        public ?RegistrationType $registrationType = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  EventPeriod|null  $defaultPeriod  used when the query names no period, state or search
     */
    public static function fromArray(array $data, ?EventPeriod $defaultPeriod = null): self
    {
        $state = EventState::tryFrom((string) ($data['state'] ?? ''));
        $search = filled($data['search'] ?? null) ? (string) $data['search'] : null;
        $registrationType = RegistrationType::tryFrom((string) ($data['registration_type'] ?? ''));
        $unfiltered = ! array_key_exists('period', $data) && $state === null && $search === null && $registrationType === null;

        return new self(
            state: $state,
            search: $search,
            period: EventPeriod::tryFrom((string) ($data['period'] ?? '')) ?? ($unfiltered ? $defaultPeriod : null),
            registrationType: $registrationType,
        );
    }

    public function toArray(): array
    {
        return [
            'state' => $this->state?->value,
            'search' => $this->search,
            'period' => $this->period?->value,
            'registration_type' => $this->registrationType?->value,
        ];
    }
}
