<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * events.customizations: the client's changes to what the template's
     * template.json declares as editable (texts, colours, sections), overrides only.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->jsonb('customizations')->default('{}')->after('template_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('customizations');
        });
    }
};
