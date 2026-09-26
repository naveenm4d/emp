<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogue entry: what clients browse and pick.
        Schema::create('templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('author');
            $table->string('category', 32);
            $table->jsonb('tags')->default('[]');
            $table->unsignedInteger('price')->default(0); // minor units
            $table->char('currency', 3);
            $table->string('display_price', 64)->nullable();
            $table->string('type', 32)->default('predefined');
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->cascadeOnDelete();
            $table->text('thumbnail_url')->nullable();
            $table->uuid('latest_version_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['is_active', 'type', 'sort_order']);
        });

        // Immutable code for one version. The package files (template.json,
        // index.html, css/, js/, assets/) live on the template disk under path.
        Schema::create('template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('path');
            $table->char('checksum', 64);
            $table->timestampTz('published_at');
            $table->timestampsTz();

            $table->unique(['template_id', 'version']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->foreign('latest_version_id')->references('id')->on('template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['latest_version_id']);
        });
        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('templates');
    }
};
