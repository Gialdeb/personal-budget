<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_plan_id')->nullable()->constrained('billing_plans')->nullOnDelete();
            $table->foreignId('billing_subscription_id')->nullable()->constrained('billing_subscriptions')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transaction_id', 191)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_name', 255)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('amount', 14, 2);
            $table->string('status', 32)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('reconciliation_status', 32)->default('pending');
            $table->timestamp('reconciled_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_transaction_id']);
            $table->unique(['provider', 'provider_event_id']);
            $table->index(['provider', 'status']);
            $table->index(['user_id', 'reconciliation_status']);
            $table->index('customer_email');
            $table->index('paid_at');
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
    }
};
