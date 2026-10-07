<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subject lines for the event's invitation and reminder, for email only
     * (WhatsApp and SMS have no subject). Stored now; nothing emails yet.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('invitation_subject', 150)->nullable()->after('invitation_message');
            $table->string('reminder_subject', 150)->nullable()->after('reminder_message');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['invitation_subject', 'reminder_subject']);
        });
    }
};
