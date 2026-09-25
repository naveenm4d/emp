<?php

namespace App\Domains\Staff\Console\Commands;

use App\Core\Exceptions\DomainException;
use App\Domains\Staff\Contracts\StaffMemberServiceInterface;
use App\Domains\Staff\DTOs\CreateStaffMemberData;
use App\Domains\Staff\Enums\StaffRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('staff:create {--name=} {--email=} {--password=} {--role=super_admin}')]
#[Description('Create an EMP internal staff member (use to bootstrap the first super admin)')]
class CreateStaffMemberCommand extends Command
{
    public function handle(StaffMemberServiceInterface $staff): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email.');
        $secret = $this->option('password') ?: password('Password', required: true, validate: fn (string $v) => strlen($v) >= 8 ? null : 'Minimum 8 characters.');
        $role = $this->option('role') ?: select('Role', StaffRole::values(), default: StaffRole::SuperAdmin->value);

        try {
            $member = $staff->create(CreateStaffMemberData::fromArray([
                'name' => $name,
                'email' => $email,
                'password' => $secret,
                'role' => $role,
            ]));
        } catch (DomainException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Staff member [{$member->email}] created with role [{$member->role->value}].");

        return self::SUCCESS;
    }
}
