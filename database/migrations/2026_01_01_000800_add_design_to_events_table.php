<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every event must have its own template and there is no default to
        // backfill with, so existing rows cannot be migrated automatically.
        if (DB::table('events')->exists()) {
            throw new RuntimeException('Events without a template exist. This project is pre-launch: run `php artisan migrate:fresh --seed`.');
        }

        Schema::table('events', function (Blueprint $table) {
            $table->foreignUuid('template_version_id')->after('client_id')->constrained('template_versions')->restrictOnDelete();
            $table->string('rendered_path')->nullable();
            $table->char('rendered_hash', 64)->nullable();
            $table->timestampTz('rendered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_version_id');
            $table->dropColumn(['rendered_path', 'rendered_hash', 'rendered_at']);
        });
    }
};
