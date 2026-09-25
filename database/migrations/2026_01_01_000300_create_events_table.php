<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('state', 32)->default('draft');
            $table->unsignedInteger('max_capacity')->default(0);
            $table->boolean('require_approval')->default(false);
            $table->boolean('registration_open')->default(false);
            $table->string('event_type', 64)->nullable();
            $table->string('location_name')->nullable();
            $table->text('location_address')->nullable();
            $table->text('map_url')->nullable();
            $table->date('event_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestampsTz();

            $table->index('state');
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
