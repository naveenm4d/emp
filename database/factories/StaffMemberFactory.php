<?php

namespace Database\Factories;

use App\Domains\Staff\Enums\StaffPermission;
use App\Domains\Staff\Enums\StaffRole;
use App\Domains\Staff\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => StaffRole::Viewer,
            'permissions' => $this->permissionValues(StaffRole::Viewer->defaultPermissions()),
            'is_active' => true,
        ];
    }

    public function role(StaffRole $role): static
    {
        return $this->state([
            'role' => $role,
            'permissions' => $this->permissionValues($role->defaultPermissions()),
        ]);
    }

    public function superAdmin(): static
    {
        return $this->role(StaffRole::SuperAdmin);
    }

    /** @param list<StaffPermission> $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->state(['permissions' => $this->permissionValues($permissions)]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * @param  list<StaffPermission>  $permissions
     * @return list<string>
     */
    private function permissionValues(array $permissions): array
    {
        return array_map(fn (StaffPermission $p) => $p->value, $permissions);
    }
}
