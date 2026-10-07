<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Team accounts: a client (the account) has several users who sign in
     * (client_users). The first one is the owner; the others are invited with
     * permissions and the events they can see (client_user_event). Each
     * existing client becomes an account whose owner keeps its login.
     */
    public function up(): void
    {
        Schema::create('client_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestampTz('email_verified_at')->nullable();
            // Null until the invitation is accepted, so a pending user can't sign in.
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->string('role', 16)->default('member');
            $table->jsonb('permissions')->default('[]');
            $table->boolean('all_events')->default(false);
            $table->timestampTz('invited_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();

            $table->index('client_id');
        });

        // One owner per account.
        DB::statement("create unique index client_users_one_owner on client_users (client_id) where role = 'owner'");

        Schema::create('client_user_event', function (Blueprint $table) {
            $table->foreignUuid('client_user_id')->constrained('client_users')->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained('events')->restrictOnDelete();

            $table->primary(['client_user_id', 'event_id']);
        });

        Schema::create('client_invitation_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            // Staff override of the plan's user limit (required for Enterprise).
            $table->unsignedInteger('user_limit')->nullable();
        });

        foreach (DB::table('clients')->get() as $client) {
            DB::table('client_users')->insert([
                'id' => (string) Str::uuid(),
                'client_id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'email_verified_at' => $client->email_verified_at,
                'password' => $client->password,
                'remember_token' => $client->remember_token,
                'role' => 'owner',
                'permissions' => '[]',
                'all_events' => true,
                'joined_at' => $client->created_at,
                'created_at' => $client->created_at,
                'updated_at' => $client->updated_at,
            ]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'password', 'remember_token']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
        });

        foreach (DB::table('client_users')->where('role', 'owner')->get() as $owner) {
            DB::table('clients')->where('id', $owner->client_id)->update([
                'email_verified_at' => $owner->email_verified_at,
                'password' => $owner->password,
                'remember_token' => $owner->remember_token,
            ]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('user_limit');
        });

        Schema::dropIfExists('client_invitation_tokens');
        Schema::dropIfExists('client_user_event');
        Schema::dropIfExists('client_users');
    }
};
