<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff overrides of how many invitations / reminders each guest of the
     * event can get. Null = the platform default (config emp.max_*_per_guest).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_invitations_per_guest')->nullable();
            $table->unsignedSmallInteger('max_reminders_per_guest')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['max_invitations_per_guest', 'max_reminders_per_guest']);
        });
    }
};
