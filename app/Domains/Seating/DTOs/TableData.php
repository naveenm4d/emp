<?php

namespace App\Domains\Seating\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Seating\Enums\TableShape;

final readonly class TableData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public int $seat_count,
        public TableShape $shape = TableShape::Round,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            seat_count: (int) $data['seat_count'],
            shape: TableShape::tryFrom((string) ($data['shape'] ?? '')) ?? TableShape::Round,
        );
    }
}
