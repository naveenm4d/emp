<?php

namespace App\Domains\Staff\Models;

use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use Carbon\CarbonImmutable;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * An EMP platform internal team member.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property StaffRole $role
 * @property list<string> $permissions
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(StaffMemberFactory::class)]
#[Fillable(['name', 'email', 'password', 'role', 'permissions', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class StaffMember extends Authenticatable
{
    /** @use HasFactory<StaffMemberFactory> */
    use HasFactory, HasUuids;

    protected $attributes = [
        'role' => 'viewer',
        'permissions' => '[]',
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === StaffRole::SuperAdmin;
    }

    public function hasPermission(StaffPermission $permission): bool
    {
        return $this->isSuperAdmin() || in_array($permission->value, $this->permissions, true);
    }

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        return $this->isSuperAdmin() ? StaffPermission::values() : $this->permissions;
    }
}
