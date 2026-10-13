<?php

namespace App\Domains\Seating\DTOs;

use App\Core\DTOs\DataTransferObject;
use App\Domains\Seating\Enums\VenueElementKind;

final readonly class VenueElementData extends DataTransferObject
{
    public function __construct(
        public VenueElementKind $kind,
        public ?string $label,
        public int $x,
        public int $y,
        public int $width,
        public int $height,
        /** Degrees clockwise; null keeps the current turn (0 for a new element). */
        public ?int $rotation = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $label = trim((string) ($data['label'] ?? ''));

        return new self(
            kind: VenueElementKind::from((string) $data['kind']),
            label: $label === '' ? null : $label,
            x: (int) $data['x'],
            y: (int) $data['y'],
            width: (int) $data['width'],
            height: (int) $data['height'],
            rotation: isset($data['rotation']) ? (int) $data['rotation'] : null,
        );
    }
}
