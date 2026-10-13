<?php

namespace App\Domains\Template\Exceptions;

use App\Core\Exceptions\ConflictException;
use App\Domains\Template\Models\Template;

class TemplatePurchaseRequiredException extends ConflictException
{
    protected string $errorCode = 'TEMPLATE_PURCHASE_REQUIRED';

    protected string $defaultMessage = 'Buy this design to use it.';

    public static function for(Template $template): self
    {
        $price = $template->display_price ? " ({$template->display_price})" : '';

        return new self("{$template->name} is a paid design{$price}. Buy it under Invitation designs to use it.");
    }
}
