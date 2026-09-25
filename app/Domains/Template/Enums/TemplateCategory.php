<?php

namespace App\Domains\Template\Enums;

use App\Core\Enums\Concerns\HasValues;

enum TemplateCategory: string
{
    use HasValues;

    case Wedding = 'wedding';
    case Engagement = 'engagement';
    case Birthday = 'birthday';
    case BabyShower = 'baby_shower';
    case Anniversary = 'anniversary';
    case Corporate = 'corporate';
    case Party = 'party';
    case Other = 'other';
}
