<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How many plus-ones and children the client invites a guest with; the
     * guest can answer up to these. Null = the event's settings apply.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->unsignedSmallInteger('invited_additional_guests')->nullable();
            $table->unsignedSmallInteger('invited_children')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['invited_additional_guests', 'invited_children']);
        });
    }
};
