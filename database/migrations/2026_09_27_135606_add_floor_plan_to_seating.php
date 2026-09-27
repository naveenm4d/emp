<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The seating floor plan: where each table stands, and the venue elements
     * (stage, poruwa, buffet, entrance, …) placed around them. Positions are
     * in canvas units on a fixed 4000×2400 canvas.
     */
    public function up(): void
    {
        Schema::table('event_tables', function (Blueprint $table) {
            // null = not placed yet; the map lays it out in order.
            $table->integer('pos_x')->nullable();
            $table->integer('pos_y')->nullable();
        });

        Schema::create('venue_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('label', 40)->nullable();
            $table->integer('pos_x');
            $table->integer('pos_y');
            $table->integer('width');
            $table->integer('height');
            $table->timestampsTz();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_elements');

        Schema::table('event_tables', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y']);
        });
    }
};
