<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online payments through PayHere: prices staff edit, saved cards
     * (PayHere customer tokens), Business subscriptions that renew on their
     * own, every payment and every PayHere notification. Paid templates a
     * client bought are kept in client_templates.
     *
     * Also grants the new billing permissions to existing admins, because
     * staff permissions are stored per account.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Who pays: PayHere asks for a phone, address and city with every checkout.
            $table->jsonb('billing_details')->nullable();
        });

        Schema::create('billing_prices', function (Blueprint $table) {
            $table->id();
            $table->string('product', 40)->unique();
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        $now = now();
        DB::table('billing_prices')->insert(array_map(fn (array $price) => [
            ...$price, 'currency' => 'LKR', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], [
            ['product' => 'business_monthly', 'amount' => 1499000],
            ['product' => 'business_yearly', 'amount' => 14990000],
            ['product' => 'celebration_event', 'amount' => 499000],
            ['product' => 'extra_guest_block', 'amount' => 100000],
        ]));

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->text('customer_token')->nullable(); // encrypted; wiped when the card is removed
            $table->string('card_holder_name')->nullable();
            $table->string('brand', 20)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignUuid('added_by')->nullable()->constrained('client_users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['client_id', 'deleted_at']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->string('plan', 32);
            $table->string('interval', 16);
            $table->string('next_interval', 16)->nullable();
            $table->string('status', 16);
            $table->boolean('auto_renew')->default(true);
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->timestampTz('current_period_start');
            $table->timestampTz('current_period_end');
            $table->timestampTz('canceled_at')->nullable();
            $table->timestampTz('renewal_reminded_at')->nullable();
            $table->string('ended_reason', 32)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'current_period_end']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('order_id', 40)->unique();
            $table->string('purpose', 24);
            $table->string('channel', 16);
            $table->string('status', 16);
            $table->string('description', 255);
            $table->string('subject_type', 120)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestampTz('renewal_period_end')->nullable();
            $table->jsonb('details')->nullable();
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->foreignUuid('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignUuid('initiated_by')->nullable()->constrained('client_users')->nullOnDelete();
            $table->string('payhere_payment_id', 64)->nullable();
            $table->string('status_message', 500)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->timestampsTz();

            // One renewal charge per subscription period, whatever happens.
            $table->unique(['subscription_id', 'renewal_period_end']);
            $table->index(['client_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 64)->nullable()->index();
            $table->jsonb('payload');
            $table->boolean('signature_valid');
            $table->string('error', 255)->nullable();
            $table->timestampTz('created_at');
        });

        Schema::create('client_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->unique(['client_id', 'template_id']);
        });

        foreach (DB::table('staff_members')->where('role', 'admin')->get(['id', 'permissions']) as $member) {
            $permissions = json_decode((string) $member->permissions, true) ?: [];
            $granted = array_values(array_unique([...$permissions, 'billing.read', 'billing.prices', 'billing.refund']));

            DB::table('staff_members')->where('id', $member->id)->update(['permissions' => json_encode($granted)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_templates');
        Schema::dropIfExists('payment_notifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('billing_prices');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('billing_details');
        });
    }
};
