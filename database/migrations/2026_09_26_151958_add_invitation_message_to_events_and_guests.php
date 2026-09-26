<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The WhatsApp text sent with an RSVP link: events.invitation_message is the
     * event's default, guests.invitation_message a per-guest override.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('invitation_message')->nullable();
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->text('invitation_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('invitation_message');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('invitation_message');
        });
    }
};
