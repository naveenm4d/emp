<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Guest-list-only events have no approval: approve guests left unapproved on them. */
    public function up(): void
    {
        DB::table('guests')
            ->where('approval_status', '!=', 'approved')
            ->whereIn('event_id', DB::table('events')->where('registration_type', 'guest_list_only')->select('id'))
            ->update(['approval_status' => 'approved', 'approval_status_changed_at' => now()]);
    }

    public function down(): void
    {
        // Previous approval statuses are not kept.
    }
};
