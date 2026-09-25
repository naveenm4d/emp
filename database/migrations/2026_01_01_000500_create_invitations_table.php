<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('token', 64)->unique();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->index(['event_id', 'status']);
        });

        // Only one active (pending / sent) invitation per guest.
        DB::statement("CREATE UNIQUE INDEX invitations_guest_active_unique ON invitations (guest_id) WHERE status IN ('pending', 'sent')");
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
