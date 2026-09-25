<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('source', 32);
            $table->string('approval_status', 32);
            $table->string('rsvp_status', 32)->default('not_sent');
            $table->string('check_in_status', 32)->default('not_checked_in');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'approval_status']);
            $table->index(['event_id', 'source']);
            $table->index(['event_id', 'rsvp_status']);
        });

        // A guest's email / phone must be unique within an event.
        DB::statement('CREATE UNIQUE INDEX guests_event_email_unique ON guests (event_id, lower(email)) WHERE email IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX guests_event_phone_unique ON guests (event_id, phone) WHERE phone IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
