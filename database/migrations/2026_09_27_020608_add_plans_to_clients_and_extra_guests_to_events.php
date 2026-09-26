<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plans (ClientPlan): which plan a client is on, until when (subscriptions)
     * and how many paid events they have left (Celebration). Events can buy
     * extra guests on top of the plan's limit.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('plan', 32)->default('starter');
            $table->timestampTz('plan_expires_at')->nullable();
            $table->unsignedInteger('event_credits')->default(0);
        });

        // Clients from before plans keep everything they had: Business, no end date.
        DB::table('clients')->update(['plan' => 'business']);

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('extra_guests')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('extra_guests');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_expires_at', 'event_credits']);
        });
    }
};
