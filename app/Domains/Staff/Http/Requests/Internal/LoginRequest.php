<?php

namespace App\Domains\Staff\Http\Requests\Internal;

use App\Core\Http\Requests\LoginRequest as BaseLoginRequest;

class LoginRequest extends BaseLoginRequest
{
    protected function guard(): string
    {
        return 'staff';
    }

    protected function credentialConstraints(): array
    {
        return ['is_active' => true];
    }
}
