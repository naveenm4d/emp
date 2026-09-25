<?php

namespace App\Domains\Template\Enums;

use App\Core\Enums\Concerns\HasValues;

enum MediaType: string
{
    use HasValues;

    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
}
