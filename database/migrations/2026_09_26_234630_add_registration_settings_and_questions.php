<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an event collects from guests when they register or RSVP:
     * - events.event_registration_settings: built-in fields (contact, attendance, party, dietary); null = defaults for the event type.
     * - events.responses_lock_at: after this, guests can no longer change their answer.
     * - registration_questions / registration_answers: the client's custom questions and each guest's answers.
     * - guests: the built-in details a guest gave.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->jsonb('event_registration_settings')->nullable();
            $table->timestampTz('responses_lock_at')->nullable();
        });

        Schema::create('registration_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('label', 255);
            $table->string('type', 32);
            $table->jsonb('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['event_id', 'sort_order']);
        });

        Schema::create('registration_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('registration_questions')->cascadeOnDelete();
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['guest_id', 'question_id']);
            $table->index('question_id');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->text('address')->nullable();
            $table->string('company', 255)->nullable();
            $table->string('job_title', 255)->nullable();
            $table->unsignedSmallInteger('additional_guests')->default(0);
            $table->unsignedSmallInteger('children')->default(0);
            $table->jsonb('dietary_restrictions')->nullable();
            $table->text('dietary_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['address', 'company', 'job_title', 'additional_guests', 'children', 'dietary_restrictions', 'dietary_notes']);
        });

        Schema::dropIfExists('registration_answers');
        Schema::dropIfExists('registration_questions');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['event_registration_settings', 'responses_lock_at']);
        });
    }
};
