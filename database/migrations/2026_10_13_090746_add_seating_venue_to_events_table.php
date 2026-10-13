<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the seating floor plan is drawn as a hall (indoor) or a
     * garden (outdoor) in the 3D view.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('seating_venue', 16)->default('indoor');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('seating_venue');
        });
    }
};
