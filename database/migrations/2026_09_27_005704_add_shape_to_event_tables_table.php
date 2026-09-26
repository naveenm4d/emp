<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A table's shape (round, oval, square, rectangle), for the seating plan drawing. */
    public function up(): void
    {
        Schema::table('event_tables', function (Blueprint $table) {
            $table->string('shape', 16)->default('round');
        });
    }

    public function down(): void
    {
        Schema::table('event_tables', function (Blueprint $table) {
            $table->dropColumn('shape');
        });
    }
};
