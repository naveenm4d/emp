<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A downgrade waiting for the current plan to end (scheduled_plan), and
     * what an Enterprise renewal costs and covers (staff set both).
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('scheduled_plan', 32)->nullable();
            $table->unsignedInteger('renewal_price')->nullable();
            $table->unsignedSmallInteger('renewal_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['scheduled_plan', 'renewal_price', 'renewal_months']);
        });
    }
};
