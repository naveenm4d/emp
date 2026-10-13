<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Grants the new clients.create permission to existing admins, because
     * staff permissions are stored per account.
     */
    public function up(): void
    {
        foreach (DB::table('staff_members')->where('role', 'admin')->get(['id', 'permissions']) as $member) {
            $permissions = json_decode((string) $member->permissions, true) ?: [];
            $granted = array_values(array_unique([...$permissions, 'clients.create']));

            DB::table('staff_members')->where('id', $member->id)->update(['permissions' => json_encode($granted)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('staff_members')->get(['id', 'permissions']) as $member) {
            $permissions = json_decode((string) $member->permissions, true) ?: [];
            $kept = array_values(array_diff($permissions, ['clients.create']));

            DB::table('staff_members')->where('id', $member->id)->update(['permissions' => json_encode($kept)]);
        }
    }
};
