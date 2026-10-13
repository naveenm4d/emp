<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far a venue element is turned on the floor plan, in degrees
     * clockwise (0–359).
     */
    public function up(): void
    {
        Schema::table('venue_elements', function (Blueprint $table) {
            $table->smallInteger('rotation')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('venue_elements', function (Blueprint $table) {
            $table->dropColumn('rotation');
        });
    }
};
