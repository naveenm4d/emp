<?php

namespace App\Domains\Client\Exceptions;

use App\Core\Exceptions\ForbiddenException;
use App\Domains\Client\Enums\PlanFeature;

class PlanFeatureUnavailableException extends ForbiddenException
{
    protected string $errorCode = 'PLAN_FEATURE_UNAVAILABLE';

    protected string $defaultMessage = 'Your plan doesn\'t include this.';

    public static function for(PlanFeature $feature): self
    {
        return new self("{$feature->label()} is available from the Celebration plan.");
    }
}
