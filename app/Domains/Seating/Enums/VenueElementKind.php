<?php

namespace App\Domains\Seating\Enums;

use App\Core\Enums\Concerns\HasValues;

/** A venue element on the seating floor plan: anything that isn't a guest table. */
enum VenueElementKind: string
{
    use HasValues;

    case Stage = 'stage';
    case Poruwa = 'poruwa';
    case DanceFloor = 'dance_floor';
    case Buffet = 'buffet';
    case Bar = 'bar';
    case CakeTable = 'cake_table';
    case Dj = 'dj';
    case PhotoBooth = 'photo_booth';
    case Entrance = 'entrance';
    case Registration = 'registration';
    case Restrooms = 'restrooms';
    /** Named by the client ("Gift table", "Kids corner"). */
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Stage => 'Stage',
            self::Poruwa => 'Poruwa',
            self::DanceFloor => 'Dance floor',
            self::Buffet => 'Buffet',
            self::Bar => 'Bar',
            self::CakeTable => 'Cake table',
            self::Dj => 'DJ / band',
            self::PhotoBooth => 'Photo booth',
            self::Entrance => 'Entrance',
            self::Registration => 'Registration',
            self::Restrooms => 'Restrooms',
            self::Custom => 'Custom',
        };
    }

    /**
     * Width and height when first placed, in canvas units.
     *
     * @return array{int, int}
     */
    public function defaultSize(): array
    {
        return match ($this) {
            self::Stage => [360, 140],
            self::Poruwa => [180, 180],
            self::DanceFloor => [300, 240],
            self::Buffet => [320, 100],
            self::Bar => [220, 90],
            self::CakeTable, self::Dj => [140, 100],
            self::PhotoBooth, self::Registration => [160, 100],
            self::Entrance, self::Restrooms => [140, 80],
            self::Custom => [180, 120],
        };
    }

    /** @return list<array{value: string, label: string, width: int, height: int}> */
    public static function options(): array
    {
        return array_map(function (self $kind) {
            [$width, $height] = $kind->defaultSize();

            return ['value' => $kind->value, 'label' => $kind->label(), 'width' => $width, 'height' => $height];
        }, self::cases());
    }
}
