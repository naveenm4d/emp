<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - events.registration_type replaces require_approval: open, approval_required or guest_list_only.
     * - Reminder texts and automatic reminder settings on events, custom reminder texts on guests.
     * - rsvps: how often and when a link was reminded; the auto stamps keep each automatic reminder to once per link.
     * - guests.approval_status_changed_at: when the approval status last changed (shown in the guest list).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('registration_type', 32)->default('guest_list_only');
            $table->text('reminder_message')->nullable();
            $table->boolean('auto_reminders')->default(false);
            $table->unsignedSmallInteger('remind_after_days')->default(3);
            $table->unsignedSmallInteger('remind_before_days')->default(2);
        });

        // Existing events keep their public page.
        DB::table('events')->update([
            'registration_type' => DB::raw("CASE WHEN require_approval THEN 'approval_required' ELSE 'open' END"),
        ]);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('require_approval');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->text('reminder_message')->nullable();
            $table->timestampTz('approval_status_changed_at')->nullable();
        });

        DB::table('guests')->update(['approval_status_changed_at' => DB::raw('created_at')]);

        Schema::table('rsvps', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminder_count')->default(0);
            $table->timestampTz('last_reminded_at')->nullable();
            $table->timestampTz('auto_after_reminded_at')->nullable();
            $table->timestampTz('auto_before_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropColumn(['reminder_count', 'last_reminded_at', 'auto_after_reminded_at', 'auto_before_reminded_at']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['reminder_message', 'approval_status_changed_at']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('require_approval')->default(false);
        });

        DB::table('events')->update(['require_approval' => DB::raw("registration_type = 'approval_required'")]);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['registration_type', 'reminder_message', 'auto_reminders', 'remind_after_days', 'remind_before_days']);
        });
    }
};
