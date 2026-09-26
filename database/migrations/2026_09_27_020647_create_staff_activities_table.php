<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The activity log: everything staff do in the admin console. Also grants
     * the new permissions (clients.plan, activity.read) to existing admins,
     * because permissions are stored per account.
     */
    public function up(): void
    {
        Schema::create('staff_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staff_member_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->string('action', 120);
            $table->string('description', 500);
            $table->string('subject_type', 120)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->jsonb('changes')->nullable();
            $table->text('note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('created_at');

            $table->index(['client_id', 'created_at']);
            $table->index(['staff_member_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        foreach (DB::table('staff_members')->where('role', 'admin')->get(['id', 'permissions']) as $member) {
            $permissions = json_decode((string) $member->permissions, true) ?: [];
            $granted = array_values(array_unique([...$permissions, 'clients.plan', 'activity.read']));

            DB::table('staff_members')->where('id', $member->id)->update(['permissions' => json_encode($granted)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activities');
    }
};
