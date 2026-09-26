<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * notifications.rsvp_id: the RSVP link a message was sent for, so the RSVP
     * views can show its delivery status. read_at: when WhatsApp reported it read.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignUuid('rsvp_id')->nullable()->after('guest_id')->constrained('rsvps')->nullOnDelete();
            $table->timestampTz('read_at')->nullable()->after('delivered_at');

            $table->index(['rsvp_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rsvp_id');
            $table->dropColumn('read_at');
        });
    }
};
