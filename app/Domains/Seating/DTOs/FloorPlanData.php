<?php

namespace App\Domains\Seating\DTOs;

use App\Core\DTOs\DataTransferObject;

/** Where the tables and venue elements stand on the seating floor plan. */
final readonly class FloorPlanData extends DataTransferObject
{
    /** Canvas size in canvas units; the map scales it to the screen. */
    public const int WIDTH = 4000;

    public const int HEIGHT = 2400;

    /**
     * @param  array<string, array{x: int, y: int}>  $tables  by table id
     * @param  array<string, array{x: int, y: int, width: int, height: int}>  $elements  by element id
     */
    public function __construct(
        public array $tables = [],
        public array $elements = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tables = [];
        $elements = [];

        foreach ($data['tables'] ?? [] as $table) {
            $tables[(string) $table['id']] = ['x' => (int) $table['x'], 'y' => (int) $table['y']];
        }

        foreach ($data['elements'] ?? [] as $element) {
            $elements[(string) $element['id']] = [
                'x' => (int) $element['x'],
                'y' => (int) $element['y'],
                'width' => (int) $element['width'],
                'height' => (int) $element['height'],
            ];
        }

        return new self($tables, $elements);
    }
}
