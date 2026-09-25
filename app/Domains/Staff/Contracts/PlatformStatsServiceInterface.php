<?php

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\DTOs\PlatformStats;

interface PlatformStatsServiceInterface
{
    public function overview(): PlatformStats;
}
