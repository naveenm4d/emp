<?php

namespace App\Domains\Client\Http\Requests\Dashboard;

/** Changing what a team member may do and which events they see. */
class UpdateMemberRequest extends MemberAccessRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->can('update', $this->route('member')) ?? false;
    }
}
