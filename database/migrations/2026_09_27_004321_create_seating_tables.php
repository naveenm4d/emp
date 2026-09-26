<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seating: an event's tables, each with a fixed number of seats, and who
     * sits where. A guest's party (plus-ones, children) sits at one table;
     * each person is one row, numbered by party_member (0 = the guest).
     */
    public function up(): void
    {
        Schema::create('event_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('seat_count');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['event_id', 'sort_order']);
        });

        DB::statement('CREATE UNIQUE INDEX event_tables_event_name_unique ON event_tables (event_id, lower(name))');

        Schema::create('seat_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('table_id')->constrained('event_tables')->cascadeOnDelete();
            $table->foreignUuid('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->unsignedSmallInteger('party_member');
            $table->unsignedSmallInteger('seat_number');
            $table->timestampsTz();

            // One person per seat, and nobody seated twice.
            $table->unique(['table_id', 'seat_number']);
            $table->unique(['guest_id', 'party_member']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_assignments');
        Schema::dropIfExists('event_tables');
    }
};
