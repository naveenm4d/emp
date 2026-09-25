<?php

namespace App\Domains\Template\Enums;

use App\Core\Enums\Concerns\HasValues;

enum TemplateType: string
{
    use HasValues;

    /** Built and imported by EMP; available to every client. */
    case Predefined = 'predefined';

    /** Owned by one client (reserved for client-made templates). */
    case Custom = 'custom';
}
