<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** An optional note the guest leaves for the host when declining. */
    public function up(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->text('response_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropColumn('response_note');
        });
    }
};
