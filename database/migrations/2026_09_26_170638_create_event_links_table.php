<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Short links (domain/{slug}/{code}). A row without a guest is the event's
     * public URL; a row with a guest is that guest's RSVP link. The code, not
     * the slug, identifies the event, so slugs no longer need to be unique.
     * Events are soft-deleted from now on.
     */
    public function up(): void
    {
        Schema::create('event_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 16)->unique();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignUuid('guest_id')->nullable()->constrained('guests')->cascadeOnDelete();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestampTz('last_opened_at')->nullable();
            $table->timestampsTz();

            $table->index('event_id');
        });

        DB::statement('CREATE UNIQUE INDEX event_links_guest_unique ON event_links (guest_id) WHERE guest_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX event_links_event_public_unique ON event_links (event_id) WHERE guest_id IS NULL');

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->index('slug');
            $table->softDeletesTz();
        });

        $now = now();
        $used = [];
        $code = function () use (&$used): string {
            do {
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $code .= 'abcdefghjkmnpqrstuvwxyz23456789'[random_int(0, 30)];
                }
            } while (isset($used[$code]));

            return $used[$code] = $code;
        };

        foreach (DB::table('events')->pluck('id') as $eventId) {
            DB::table('event_links')->insert(['id' => (string) Str::uuid7(), 'code' => $code(), 'event_id' => $eventId, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (DB::table('guests')->select('id', 'event_id')->get() as $guest) {
            DB::table('event_links')->insert(['id' => (string) Str::uuid7(), 'code' => $code(), 'event_id' => $guest->event_id, 'guest_id' => $guest->id, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_links');

        Schema::table('events', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
            $table->dropIndex(['slug']);
            $table->unique('slug');
        });
    }
};
