<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Checkouts waiting for PayHere's answer: what is being bought, for whom
     * and for how much, until PayHere processes it. Only then does a payment
     * exist (paid or declined); abandoned checkouts are pruned after a day.
     */
    public function up(): void
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('order_id', 40)->unique();
            $table->string('purpose', 24);
            $table->string('channel', 16);
            $table->string('description', 255);
            $table->string('subject_type', 120)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->cascadeOnDelete();
            $table->timestampTz('renewal_period_end')->nullable();
            $table->jsonb('details')->nullable();
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignUuid('initiated_by')->nullable()->constrained('client_users')->nullOnDelete();
            $table->string('payhere_payment_id', 64)->nullable();
            $table->timestampsTz();

            // One renewal attempt per subscription period.
            $table->unique(['subscription_id', 'renewal_period_end']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkouts');
    }
};
