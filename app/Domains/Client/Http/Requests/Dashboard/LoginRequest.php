<?php

namespace App\Domains\Client\Http\Requests\Dashboard;

use App\Core\Http\Requests\LoginRequest as BaseLoginRequest;

class LoginRequest extends BaseLoginRequest
{
    protected function guard(): string
    {
        return 'client';
    }
}
