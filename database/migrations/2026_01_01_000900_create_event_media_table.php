<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Files a client uploaded into a template's media slots.
        Schema::create('event_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('slot_key', 64);
            $table->string('type', 16);
            $table->string('disk', 64);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->timestampsTz();

            // One file per placeholder; uploading again replaces it.
            $table->unique(['event_id', 'slot_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_media');
    }
};
