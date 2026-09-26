<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** notifications.kind: what a message was (rsvp_invitation, rsvp_reminder); null for older rows. */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('kind', 32)->nullable()->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
